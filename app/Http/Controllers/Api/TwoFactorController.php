<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;
use PragmaRX\Google2FAQRCode\QRCode\Bacon as BaconQrCodeService;

class TwoFactorController extends Controller
{
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA sudah aktif.'], 422);
        }

        $secret = $user->two_factor_secret ?: $this->google2fa()->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
        ])->save();

        $twoFa = $this->google2fa();

        return response()->json([
            'secret' => $secret,
            // QR code SVG inline (tidak butuh Imagick).
            'qr_svg' => $twoFa->getQRCodeInline(config('app.name'), $user->email, $secret, 200),
            'qr_content' => $twoFa->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'message' => 'Scan QR dengan aplikasi authenticator lalu konfirmasi kode 6 digit.',
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Setup 2FA belum dimulai.'], 422);
        }

        $valid = $this->google2fa()->verifyKey($user->two_factor_secret, $data['code']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Kode TOTP tidak valid.',
            ]);
        }

        $user->forceFill(['two_factor_enabled' => true])->save();

        return response()->json([
            'message' => '2FA berhasil diaktifkan.',
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_enabled) {
            return response()->json(['message' => '2FA belum aktif.'], 422);
        }

        if (! $this->google2fa()->verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Kode TOTP tidak valid.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
        ])->save();

        return response()->json([
            'message' => '2FA berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Step kedua login: verifikasi TOTP setelah kredensial email/password valid.
     * Menerima user_id + kode; sesi belum dibuat hingga lolos.
     */
    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'digits:6'],
            'remember' => ['boolean'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return response()->json(['message' => '2FA tidak aktif untuk akun ini.'], 422);
        }

        if (! $this->google2fa()->verifyKey($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Kode TOTP tidak valid.',
            ]);
        }

        Auth::login($user, $data['remember'] ?? false);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'is_superadmin' => $user->isSuperAdmin(),
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])->values(),
                'permissions' => $user->permissionSlugs(),
            ],
        ]);
    }

    /**
     * Instance Google2FA dengan QR service Bacon + SVG backend
     * (tidak butuh ekstensi Imagick).
     */
    private function google2fa(): Google2FA
    {
        $service = new BaconQrCodeService(new SvgImageBackEnd());

        $twoFa = new Google2FA;
        $twoFa->setQrCodeService($service);

        return $twoFa;
    }
}
