<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissionSlugs): User
    {
        foreach ($permissionSlugs as $i => $slug) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'test']);
        }

        $role = Role::create([
            'name' => 'Role '.uniqid(),
            'slug' => 'role-'.uniqid(),
        ]);

        $role->permissions()->sync(Permission::whereIn('slug', $permissionSlugs)->pluck('id'));

        $user = User::create([
            'name' => 'Pegawai',
            'email' => uniqid().'@example.test',
            'password' => 'SI_SECRET_PASSWORD_VHS0BTQ9',
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function superadmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Super Admin', 'is_system' => true]);

        $user = User::create([
            'name' => 'Super',
            'email' => uniqid().'super@example.test',
            'password' => 'SI_SECRET_PASSWORD_CVNQKZ9B',
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_user_tanpa_permission_ditolak_403(): void
    {
        $user = $this->userWithPermissions([]);
        $this->actingAs($user);

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_user_dengan_permission_bisa_akses(): void
    {
        $user = $this->userWithPermissions(['users.view']);
        $this->actingAs($user);

        $this->getJson('/api/users')->assertOk();
    }

    public function test_superadmin_lolos_semua_permission(): void
    {
        $this->actingAs($this->superadmin());

        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/roles')->assertOk();
    }

    public function test_hasPermission_bekerja_per_slug(): void
    {
        $user = $this->userWithPermissions(['users.view', 'users.create']);

        $this->assertTrue($user->hasPermission('users.view'));
        $this->assertTrue($user->hasPermission('users.create'));
        $this->assertFalse($user->hasPermission('users.delete'));
        $this->assertFalse($user->hasPermission('roles.view'));
    }
}
