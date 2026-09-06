<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\SyncTombstone;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Syncable
{
    public static function bootSyncable(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::deleted(function (Model $model) {
            if (! empty($model->uuid)) {
                $teamId = ($model instanceof Team)
                    ? $model->id
                    : ($model->team_id ?? (method_exists($model, 'team') ? $model->team?->id : null));

                SyncTombstone::create([
                    'team_id' => $teamId,
                    'entity_type' => class_basename($model),
                    'entity_uuid' => $model->uuid,
                    'deleted_at' => now(),
                ]);
            }
        });
    }
}
