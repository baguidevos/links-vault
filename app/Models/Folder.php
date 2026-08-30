<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\AddTeamId;
use App\Concerns\AddUserId;
use App\Concerns\BelongsToTeam;
use App\Enums\FolderRole;
use App\Enums\FolderVisibility;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    use AddTeamId, AddUserId, BelongsToTeam, HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'category_id',
        'name',
        'slug',
        'color',
        'icon',
        'description',
        'visibility',
        'sort_order',
    ];

    protected $casts = [
        'visibility' => FolderVisibility::class,
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'folder_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Scope pour filtrer les dossiers accessibles à un utilisateur donné.
     */
    public function scopeAccessibleForUser(Builder $query, User $user, ?Model $team = null): Builder
    {
        $tenant = $team ?: (class_exists(Filament::class) ? Filament::getTenant() : null);

        // Si l'utilisateur est le propriétaire de cette équipe active
        if ($tenant && $user->ownsTeam($tenant)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('visibility', FolderVisibility::Team->value)
                ->orWhere('visibility', FolderVisibility::Team)
                ->orWhere(function (Builder $subQuery) use ($user) {
                    $subQuery->where(function ($sq) {
                        $sq->where('visibility', FolderVisibility::Restricted->value)
                            ->orWhere('visibility', FolderVisibility::Restricted);
                    })->whereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
                });
        });
    }

    /**
     * Vérifie si un utilisateur peut voir ce dossier.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->team && $user->ownsTeam($this->team)) {
            return true;
        }

        $vis = $this->visibility instanceof FolderVisibility ? $this->visibility->value : (string) $this->visibility;

        if ($vis === FolderVisibility::Team->value) {
            return $this->team ? $user->belongsToTeam($this->team) : true;
        }

        if ($vis === FolderVisibility::Restricted->value) {
            return $this->members()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Vérifie si un utilisateur peut éditer/ajouter du contenu dans ce dossier.
     */
    public function canBeEditedBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->team && $user->ownsTeam($this->team)) {
            return true;
        }

        $vis = $this->visibility instanceof FolderVisibility ? $this->visibility->value : (string) $this->visibility;

        if ($vis === FolderVisibility::Team->value) {
            return true;
        }

        if ($vis === FolderVisibility::Restricted->value) {
            $member = $this->members()->where('users.id', $user->id)->first();

            return $member && ($member->pivot->role === FolderRole::Editor->value || $member->pivot->role === 'editor');
        }

        return false;
    }
}
