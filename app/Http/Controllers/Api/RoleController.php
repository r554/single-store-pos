<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->with('permissions:id,name,slug,group')
            ->orderBy('name')
            ->get();

        return response()->json([
            'roles' => $roles,
            'permissions' => $this->groupedPermissions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

        return response()->json([
            'message' => 'Role berhasil dibuat.',
            'role' => $role->loadCount(['users', 'permissions'])->load('permissions:id,name,slug,group'),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validateData($request, $role);

        if ($role->isSystem()) {
            unset($data['slug']);
        }

        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $role->slug,
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

        return response()->json([
            'message' => 'Role berhasil diperbarui.',
            'role' => $role->loadCount(['users', 'permissions'])->load('permissions:id,name,slug,group'),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->isSystem()) {
            return response()->json(['message' => 'Role sistem tidak dapat dihapus.'], 422);
        }

        // Hanya hitung pengguna aktif (bukan yang di-trash).
        if ($role->users()->whereNull('users.deleted_at')->exists()) {
            return response()->json(['message' => 'Role masih dipakai oleh pengguna.'], 422);
        }

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus.']);
    }

    private function validateData(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
    }

    private function permissionIds(array $slugsOrIds): array
    {
        return collect($slugsOrIds)->map(fn ($id) => (int) $id)->unique()->all();
    }

    private function groupedPermissions(): array
    {
        return Permission::orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'group', 'description'])
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => $items->values(),
            ])
            ->values()
            ->all();
    }
}
