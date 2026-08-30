<?php

declare(strict_types=1);

namespace App\Concerns\TeamConcerns;

use App\Models\TeamMember;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use LaravelDaily\FilaTeams\Contracts\TeamPermissionContract;
use LaravelDaily\FilaTeams\Facades\FilaTeams;
use LaravelDaily\FilaTeams\Models\Membership;
use LaravelDaily\FilaTeams\Models\Team;

trait HasTeams
{
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Membership::class)->where('role', FilaTeams::ownerRole()->value);
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function personalTeam(): ?Model
    {
        return $this->teams()->where('is_personal', true)->first();
    }

    public function switchTeam(Model|Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill(['current_team_id' => $team->id])->save();

        $this->setRelation('currentTeam', $team);

        return true;
    }

    public function belongsToTeam(Model|Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    public function isCurrentTeam(Model|Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    public function ownsTeam(Model|Team $team): bool
    {
        $role = $this->teamRole($team);

        if (! $role) {
            return false;
        }

        $roleValue = $role instanceof BackedEnum ? $role->value : (string) $role;

        return $roleValue === 'owner';
    }

    public function isCurrentTeamOwner(?Model $team = null): bool
    {
        $targetTeam = $team;

        if (! $targetTeam && class_exists(Filament::class)) {
            $targetTeam = Filament::getTenant();
        }

        if (! $targetTeam) {
            $targetTeam = $this->currentTeam;
        }

        return $targetTeam ? $this->ownsTeam($targetTeam) : false;
    }

    public function teamRole(Model|Team $team): mixed
    {
        $membership = TeamMember::where('user_id', $this->id)
            ->where('team_id', $team->id)
            ->first();

        return $membership?->role;
    }

    public function hasTeamPermission(Model|Team $team, string|TeamPermissionContract $permission): bool
    {
        $role = $this->teamRole($team);
        $value = $permission instanceof BackedEnum ? $permission->value : $permission;

        return $role !== null && $role->hasPermission($value);
    }

    public function fallbackTeam(?Model $excluding = null): ?Model
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderBy('name')
            ->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * @return array<Model>|Collection
     */
    public function getTenants(Panel $panel): array|Collection
    {
        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->belongsToTeam($tenant);
    }
}
