<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function givePermissionTo(Permission|string ...$permissions): void
    {
        $ids = collect($permissions)
            ->map(fn ($permission) => $permission instanceof Permission
                ? $permission->id
                : Permission::where('slug', $permission)->value('id'))
            ->filter()
            ->all();

        $this->permissions()->syncWithoutDetaching($ids);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'superadmin';
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
