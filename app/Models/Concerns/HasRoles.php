<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === 'superadmin');
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->roles->pluck('slug')->intersect($roles)->isNotEmpty();
    }

    public function hasAnyRole(iterable $roles): bool
    {
        return $this->roles->pluck('slug')->intersect($roles)->isNotEmpty();
    }

    public function permissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::all();
        }

        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions)
            ->unique('id')
            ->values();
    }

    public function permissionSlugs(): array
    {
        return $this->permissions()->pluck('slug')->all();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissions()->contains('slug', $permission);
    }

    public function assignRole(Role|string ...$roles): void
    {
        $ids = collect($roles)
            ->map(fn ($role) => $role instanceof Role ? $role->id : Role::where('slug', $role)->value('id'))
            ->filter()
            ->all();

        $this->roles()->syncWithoutDetaching($ids);
    }

    public function syncRoles(iterable $roles): void
    {
        $ids = collect($roles)
            ->map(fn ($role) => $role instanceof Role ? $role->id : Role::where('slug', $role)->value('id'))
            ->filter()
            ->unique()
            ->all();

        $this->roles()->sync($ids);
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->hasPermission($permission);
    }
}
