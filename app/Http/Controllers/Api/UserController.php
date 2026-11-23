<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();
        $roleFilter = $request->string('role')->trim()->value();

        $users = User::query()
            ->with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter, fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('slug', $roleFilter)))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => User::passwordRules(),
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'password_change_required' => true,
        ]);

        $user->syncRoles($data['roles'] ?? []);

        return response()->json([
            'message' => 'Pengguna berhasil dibuat.',
            'user' => $this->payload($user->load('roles')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', ...User::passwordRules()],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        return response()->json([
            'message' => 'Pengguna berhasil diperbarui.',
            'user' => $this->payload($user->load('roles')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Akun superadmin tidak dapat dihapus.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Pengguna berhasil dihapus dan dipindahkan ke sampah.']);
    }

    public function trash(): JsonResponse
    {
        $search = request()->string('search')->trim()->value();

        $users = User::onlyTrashed()
            ->with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return response()->json($users);
    }

    public function restore(string $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);

        if ($user->trashed() && User::where('email', $user->email)->whereNull('deleted_at')->exists()) {
            return response()->json([
                'message' => 'Email sudah dipakai akun aktif lain. Ubah email akun tersebut dahulu.',
            ], 422);
        }

        $user->restore();

        return response()->json(['message' => 'Pengguna berhasil dipulihkan.']);
    }

    public function forceDelete(string $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);

        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Akun superadmin tidak dapat dihapus permanen.'], 422);
        }

        $user->roles()->detach();
        $user->forceDelete();

        return response()->json(['message' => 'Pengguna berhasil dihapus permanen.']);
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fileName = 'users-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM agar Excel membaca benar
            fputcsv($out, ['ID', 'Nama', 'Email', 'Telepon', 'Aktif', 'Role', 'Dibuat', 'Login Terakhir']);

            User::with('roles')->orderBy('id')->chunk(500, function ($users) use ($out) {
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->is_active ? 'ya' : 'tidak',
                        $user->roles->pluck('name')->implode('; '),
                        $user->created_at?->toDateTimeString(),
                        $user->last_login_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['message' => 'File tidak dapat dibaca.'], 422);
        }

        // Lewati header; buang BOM bila ada.
        $header = fgetcsv($handle);
        if ($header !== false && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;

            // Format: nama, email, telepon, role (slug dipisah ;), password, aktif
            [$name, $email, $phone, $rolesCsv, $password, $active] = array_pad(array_map('trim', $data), 6, null);

            if (empty($name) || empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $existing = User::withTrashed()->where('email', $email)->first();

            if ($existing && ! $existing->trashed()) {
                $skipped++;
                continue;
            }

            $user = $existing ?? new User();
            $user->fill([
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: null,
                'is_active' => in_array(strtolower((string) $active), ['ya', '1', 'true', 'yes'], true),
            ]);

            if (! $existing || empty($user->password)) {
                $user->password = Hash::make($password ?: bin2hex(random_bytes(6)).'Aa1!');
            }

            if ($existing && $existing->trashed()) {
                $user->restore();
            }

            $user->save();

            $roleSlugs = array_filter(array_map('trim', explode(';', (string) $rolesCsv)));
            if ($roleSlugs) {
                $user->syncRoles($roleSlugs);
            }

            $existing ? $updated++ : $created++;
        }

        fclose($handle);

        return response()->json([
            'message' => "Impor selesai: {$created} dibuat, {$updated} dipulihkan, {$skipped} dilewati.",
        ]);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
        ];
    }
}
