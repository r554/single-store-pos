<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(function (Model $model) {
            $model->recordActivity('created', "{$model->activityLabel} dibuat.");
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            unset($changes['updated_at'], $changes['last_login_at']);

            if (empty($changes)) {
                return;
            }

            $fields = array_keys($changes);
            $model->recordActivity('updated', "{$model->activityLabel} diperbarui.", ['fields' => $fields]);
        });

        static::deleted(function (Model $model) {
            $model->recordActivity('deleted', "{$model->activityLabel} dihapus.");
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->recordActivity('restored', "{$model->activityLabel} dipulihkan.");
            });
        }

        if (method_exists(static::class, 'forceDeleted')) {
            static::forceDeleted(function (Model $model) {
                $model->recordActivity('force_deleted', "{$model->activityLabel} dihapus permanen.");
            });
        }
    }

    public function recordActivity(string $action, string $description, array $properties = []): void
    {
        ActivityLog::record(
            auth()->user(),
            $action,
            $description,
            $this,
            $properties
        );
    }

    public function getActivityLabelAttribute(): string
    {
        $class = class_basename($this::class);

        return match ($class) {
            'User' => 'Pengguna',
            'Role' => 'Role',
            'Permission' => 'Permission',
            default => $class,
        };
    }
}
