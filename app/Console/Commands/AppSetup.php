<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AppSetup extends Command
{
    protected $signature = 'app:setup
        {--skip-admin : Lewati pembuatan akun admin}';

    protected $description = 'Setup starter template: migrate, seed, buat akun admin, dan pengaturan dasar';

    public function handle(): int
    {
        $this->info('== Setup RBAC Starter ==');

        // 1. Migrasi + seed
        $this->info('[1/3] Menjalankan migrasi & seeder...');
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);
        $this->call('storage:link');

        // 2. Cek akun superadmin
        if (! $this->option('skip-admin')) {
            $this->ensureAdmin();
        } else {
            $this->line('[2/3] Lewati pembuatan admin (--skip-admin).');
        }

        // 3. Pengaturan default
        $this->info('[3/3] Menyiapkan pengaturan default...');
        $this->ensureDefaultSettings();

        $this->newLine();
        $this->info('Selesai! Aplikasi siap dipakai.');
        $this->line('Login: '.config('app.url').'/login');

        return self::SUCCESS;
    }

    private function ensureAdmin(): void
    {
        $existing = User::withTrashed()->where('email', 'superadmin@example.com')->first();

        if ($existing && ! $existing->trashed()) {
            if ($this->confirm('Akun superadmin@example.com sudah ada. Reset password-nya?', false)) {
                $password = $this->askStrongPassword();
                $existing->forceFill([
                    'password' => Hash::make($password),
                    'is_active' => true,
                ])->save();
                $this->info('Password superadmin diperbarui.');
            }

            return;
        }

        $this->info('Membuat akun superadmin...');

        $name = $this->ask('Nama superadmin', 'Super Admin');
        $email = $this->ask('Email superadmin', 'superadmin@example.com');
        $password = $this->askStrongPassword();

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
                'password_change_required' => false,
                'email_verified_at' => now(),
            ]
        );

        $superadminRole = Role::where('slug', 'superadmin')->first();
        if ($superadminRole) {
            $user->roles()->syncWithoutDetaching([$superadminRole->id]);
        }

        $this->info("Akun {$email} dibuat.");
    }

    private function askStrongPassword(): string
    {
        $validator = null;

        do {
            $password = $this->secret('Password (min 8, ada huruf besar, angka, simbol)');

            $validator = Validator::make(['password' => $password], [
                'password' => User::passwordRules(),
            ]);

            if ($validator->fails()) {
                $this->warn('Password lemah: '.implode(' ', $validator->errors()->all()));
            }
        } while ($validator->fails());

        return $password;
    }

    private function ensureDefaultSettings(): void
    {
        $defaults = [
            ['key' => 'app_name', 'value' => config('app.name'), 'type' => 'text'],
            ['key' => 'maintenance_mode', 'value' => false, 'type' => 'boolean'],
            ['key' => 'session_timeout_minutes', 'value' => 120, 'type' => 'integer'],
            ['key' => 'password_policy_min_length', 'value' => 8, 'type' => 'integer'],
        ];

        foreach ($defaults as $setting) {
            AppSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => match ($setting['type']) {
                    'boolean' => $setting['value'] ? '1' : '0',
                    'integer' => (string) $setting['value'],
                    default => $setting['value'],
                }, 'type' => $setting['type']],
            );
        }
    }
}
