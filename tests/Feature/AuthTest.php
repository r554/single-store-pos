<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'superadmin', 'is_system' => true]);
        $role->permissions()->sync(
            collect([Permission::firstOrCreate(['slug' => 'users.view'], ['name' => 'Lihat Pengguna', 'group' => 'Pengguna'])])
                ->pluck('id')
        );

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.test',
            'password' => 'Superadmin123!',
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_login_sukses_mengembalikan_user(): void
    {
        $this->superadmin();

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.test',
            'password' => 'Superadmin123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonStructure(['user' => ['id', 'email', 'roles']]);
    }

    public function test_login_password_salah_ditolak(): void
    {
        $this->superadmin();

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password-1A!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_user_nonaktif_ditolak(): void
    {
        $this->superadmin()->forceFill(['is_active' => false])->save();

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.test',
            'password' => 'Superadmin123!',
        ]);

        $response->assertUnprocessable();
    }

    public function test_register_membutuhkan_password_kuat(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Baru',
            'email' => 'baru@example.test',
            'password' => 'lemah',
            'password_confirmation' => 'lemah',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_register_sukses_membuat_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Baru',
            'email' => 'baru@example.test',
            'password' => 'Kuat123!x',
            'password_confirmation' => 'Kuat123!x',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'baru@example.test']);
    }

    public function test_me_membutuhkan_auth(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_activity_log_tercatat_saat_user_dibuat(): void
    {
        $actor = $this->superadmin();
        $this->actingAs($actor);

        $count = ActivityLog::count();

        User::create([
            'name' => 'Log Test',
            'email' => 'logtest@example.test',
            'password' => 'Kuat123!x',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('activity_logs', $count + 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created']);
    }

    public function test_soft_delete_user_muncul_di_trash(): void
    {
        $actor = $this->superadmin();
        $this->actingAs($actor);

        $target = User::create([
            'name' => 'Target',
            'email' => 'target@example.test',
            'password' => 'Kuat123!x',
            'is_active' => true,
        ]);

        $target->delete();

        $this->assertSoftDeleted($target);

        $this->getJson('/api/users/trash')
            ->assertOk()
            ->assertJsonFragment(['email' => 'target@example.test']);
    }
}
