<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'trashed_users' => User::onlyTrashed()->count(),
            ],
            'recent_users' => User::with('roles')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'roles' => $user->roles->pluck('name'),
                ]),
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
        ]);
    }
}
