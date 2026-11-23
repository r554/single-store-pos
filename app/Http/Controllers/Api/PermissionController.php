<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();
        $sort = in_array($request->query('sort'), ['name', 'group', 'slug'], true)
            ? $request->query('sort')
            : 'group';
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $dir)
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return response()->json($permissions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $permission = Permission::create($data);

        return response()->json([
            'message' => 'Permission berhasil dibuat.',
            'permission' => $permission,
        ], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $data = $this->validateData($request, $permission);

        $permission->update($data);

        return response()->json([
            'message' => 'Permission berhasil diperbarui.',
            'permission' => $permission,
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        // Lepaskan dari semua role sebelum dihapus (pivot cascade).
        $permission->roles()->detach();
        $permission->delete();

        return response()->json(['message' => 'Permission berhasil dihapus.']);
    }

    private function validateData(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('permissions', 'slug')->ignore($permission?->id),
            ],
            'group' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
