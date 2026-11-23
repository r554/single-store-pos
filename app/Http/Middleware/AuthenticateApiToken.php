<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi token API (Bearer) sebagai pelengkap session web.
 * Token dikirim via header: Authorization: Bearer <token>.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Token API tidak diberikan.'], 401);
        }

        $token = ApiToken::query()->where('token', hash('sha256', $bearer))->first();

        if (! $token || $token->isExpired()) {
            return response()->json(['message' => 'Token API tidak valid atau kedaluwarsa.'], 401);
        }

        $user = $token->user;

        if (! $user || ! $user->is_active || $user->trashed()) {
            return response()->json(['message' => 'Akun tidak aktif.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        auth()->login($user);

        // Tandai request sebagai token-authenticated (bukan session).
        config(['app.api_token_auth' => true]);

        return $next($request);
    }
}
