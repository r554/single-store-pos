<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Lihat Pengguna', 'slug' => 'users.view', 'group' => 'Pengguna'],
            ['name' => 'Tambah Pengguna', 'slug' => 'users.create', 'group' => 'Pengguna'],
            ['name' => 'Ubah Pengguna', 'slug' => 'users.update', 'group' => 'Pengguna'],
            ['name' => 'Hapus Pengguna', 'slug' => 'users.delete', 'group' => 'Pengguna'],
            ['name' => 'Lihat Role', 'slug' => 'roles.view', 'group' => 'Role'],
            ['name' => 'Tambah Role', 'slug' => 'roles.create', 'group' => 'Role'],
            ['name' => 'Ubah Role', 'slug' => 'roles.update', 'group' => 'Role'],
            ['name' => 'Hapus Role', 'slug' => 'roles.delete', 'group' => 'Role'],
            ['name' => 'Lihat Permission', 'slug' => 'permissions.view', 'group' => 'Permission'],
            ['name' => 'Tambah Permission', 'slug' => 'permissions.create', 'group' => 'Permission'],
            ['name' => 'Ubah Permission', 'slug' => 'permissions.update', 'group' => 'Permission'],
            ['name' => 'Hapus Permission', 'slug' => 'permissions.delete', 'group' => 'Permission'],
            ['name' => 'Lihat Log Aktivitas', 'slug' => 'activity.view', 'group' => 'Sistem'],
            ['name' => 'Lihat Pengaturan', 'slug' => 'settings.view', 'group' => 'Sistem'],
            ['name' => 'Ubah Pengaturan', 'slug' => 'settings.update', 'group' => 'Sistem'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $superadmin = Role::updateOrCreate(
            ['slug' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'description' => 'Memiliki akses penuh ke seluruh sistem.',
                'is_system' => true,
            ]
        );

        $superadmin->permissions()->sync(Permission::pluck('id'));

        // Role bawaan untuk kebutuhan operasional ke depan.
        Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Mengelola pengguna dan role.',
                'is_system' => true,
            ]
        )->permissions()->sync(
            Permission::whereIn('slug', [
                'users.view', 'users.create', 'users.update',
                'roles.view',
            ])->pluck('id')
        );

        // Role khusus audit: hanya melihat log aktivitas.
        Role::updateOrCreate(
            ['slug' => 'auditor'],
            [
                'name' => 'Auditor',
                'description' => 'Hanya dapat melihat log aktivitas.',
                'is_system' => true,
            ]
        )->permissions()->sync(
            Permission::whereIn('slug', ['activity.view'])->pluck('id')
        );

        Role::updateOrCreate(
            ['slug' => 'staff'],
            [
                'name' => 'Staff',
                'description' => 'Akses terbatas hanya melihat data.',
                'is_system' => true,
            ]
        )->permissions()->sync(
            Permission::whereIn('slug', ['users.view'])->pluck('id')
        );

        $user = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'phone' => '081200000000',
                'password' => Hash::make('Superadmin123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->roles()->syncWithoutDetaching([$superadmin->id]);
    }
}
