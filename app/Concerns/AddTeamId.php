<?php

namespace App\Concerns;

use Filament\Facades\Filament;

trait AddTeamId
{
    protected static function bootAddTeamId(): void
    {
        static::creating(function ($model) {
            if (empty($model->team_id)) {
                if (class_exists(Filament::class) && Filament::getTenant()) {
                    $model->team_id = Filament::getTenant()->id;
                } elseif (auth()->check() && auth()->user()->current_team_id) {
                    $model->team_id = auth()->user()->current_team_id;
                }
            }
        });
    }
}
