<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->fill($data);

        if ($user->isDirty('email') && config('auth_features.email_verification.enabled')) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($user->wasChanged('email') && config('auth_features.email_verification.enabled')) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh()->load('roles'),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [...User::passwordRules(), 'confirmed', 'different:current_password'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak cocok.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_change_required' => false,
        ])->save();

        // Regenerasi session agar sesi lama tidak valid dengan kredensial baru.
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Password berhasil diubah.',
            'password_change_required' => false,
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            $oldPath = storage_path('app/public/'.$user->avatar_path);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        return response()->json([
            'message' => 'Avatar berhasil diperbarui.',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            $path = storage_path('app/public/'.$user->avatar_path);
            if (is_file($path)) {
                @unlink($path);
            }

            $user->forceFill(['avatar_path' => null])->save();
        }

        return response()->json([
            'message' => 'Avatar berhasil dihapus.',
            'avatar_url' => '',
        ]);
    }
}
