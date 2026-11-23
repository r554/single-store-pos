<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        return User::create([
            'name' => 'Token User',
            'email' => 'token@example.test',
            'password' => 'SI_SECRET_PASSWORD_DG1FC387',
            'is_active' => true,
        ]);
    }

    public function test_bearer_token_bisa_akses_token_me(): void
    {
        $user = $this->activeUser();

        $plain = Str::random(60);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'token' => hash('sha256', $plain),
            'abilities' => ['*'],
        ]);

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/token/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_token_salah_ditolak(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong-token')
            ->getJson('/api/token/me')
            ->assertUnauthorized();
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/token/me')->assertUnauthorized();
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        $user = $this->activeUser();
        $plain = Str::random(60);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'Expired',
            'token' => hash('sha256', $plain),
            'abilities' => ['*'],
            'expires_at' => now()->subDay(),
        ]);

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/token/me')
            ->assertUnauthorized();
    }
}
