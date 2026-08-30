<?php

namespace App\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTeam
{
    /**
     * Boot the global scope for team filtering.
     */
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $query) {
            if (class_exists(Filament::class) && Filament::getTenant()) {
                $query->where($query->getModel()->getTable().'.team_id', Filament::getTenant()->id);
            } elseif (auth()->check() && auth()->user()->current_team_id) {
                $query->where($query->getModel()->getTable().'.team_id', auth()->user()->current_team_id);
            }
        });
    }

    /**
     * Scope to query without team filtering.
     */
    public function scopeWithoutTeamScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('team');
    }

    /**
     * Scope to query for a specific team.
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->withoutGlobalScope('team')->where('team_id', $teamId);
    }

    /**
     * Scope to query all teams.
     */
    public function scopeAllTeams(Builder $query): Builder
    {
        return $query->withoutGlobalScope('team');
    }
}
