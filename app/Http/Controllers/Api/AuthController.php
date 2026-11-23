<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $user = $request->user()->load('roles');

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        // 2FA aktif: logout dulu, minta kode TOTP lewat endpoint challenge.
        if ($user->two_factor_enabled && $user->two_factor_secret) {
            Auth::guard('web')->logout();

            return response()->json([
                'two_factor_required' => true,
                'user_id' => $user->id,
                'message' => 'Masukkan kode 6 digit dari aplikasi authenticator Anda.',
            ], 200);
        }

        $this->finalizeLogin($request, $user, $remember);

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $this->userPayload($user),
            'password_change_required' => (bool) $user->password_change_required,
        ]);
    }

    private function finalizeLogin(Request $request, User $user, bool $remember = false): void
    {
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
    }

    public function register(Request $request): JsonResponse
    {
        if (! config('auth_features.registration.enabled')) {
            return response()->json([
                'message' => 'Registrasi akun sedang dinonaktifkan.',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [...User::passwordRules(), 'confirmed'],
        ]);

        $data['password_change_required'] = false;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        // Kirim email verifikasi hanya saat fitur aktif (AUTH_EMAIL_VERIFICATION_ENABLED).
        if (config('auth_features.email_verification.enabled')) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registrasi berhasil. Selamat datang!',
            'user' => $this->userPayload($user->load('roles')),
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        if (! config('auth_features.password_reset.enabled')) {
            return response()->json([
                'message' => 'Reset password sedang dinonaktifkan.',
            ], 403);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        // Selalu respons sama agar tidak membocorkan keberadaan akun.
        return response()->json([
            'message' => 'Jika email terdaftar, tautan reset password telah dikirim.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        if (! config('auth_features.password_reset.enabled')) {
            return response()->json([
                'message' => 'Reset password sedang dinonaktifkan.',
            ], 403);
        }

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [...User::passwordRules(), 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login dengan password baru.',
        ]);
    }

    public function sendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email Anda sudah terverifikasi.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Tautan verifikasi baru telah dikirim ke email Anda.',
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->route('id'));

        if (! URL::hasValidSignature($request)) {
            return response()->json([
                'message' => 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email Anda sudah terverifikasi.',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email berhasil diverifikasi. Silakan login.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()->load('roles')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'is_superadmin' => $user->isSuperAdmin(),
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'password_change_required' => (bool) $user->password_change_required,
            'roles' => $user->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
            'permissions' => $user->permissionSlugs(),
        ];
    }
}
