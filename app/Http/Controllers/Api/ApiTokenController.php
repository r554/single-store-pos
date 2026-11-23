<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->apiTokens()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']);

        return response()->json(['tokens' => $tokens]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $plain = Str::random(60);

        $token = $request->user()->apiTokens()->create([
            'name' => $data['name'],
            'token' => hash('sha256', $plain),
            'abilities' => $data['abilities'] ?? ['*'],
            'expires_at' => isset($data['expires_in_days'])
                ? now()->addDays($data['expires_in_days'])
                : null,
        ]);

        return response()->json([
            'message' => 'Token berhasil dibuat. Salin sekarang — tidak akan ditampilkan lagi.',
            'token' => $plain,
            'id' => $token->id,
        ], 201);
    }

    public function destroy(Request $request, ApiToken $token): JsonResponse
    {
        if ($token->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $token->delete();

        return response()->json(['message' => 'Token berhasil dihapus.']);
    }
}
