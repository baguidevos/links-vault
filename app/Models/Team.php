<?php

namespace App\Models;

use App\Concerns\Syncable;
use App\Concerns\TeamConcerns\GeneratesUniqueTeamSlugs;
use App\FilaTeams;
use Database\Factories\TeamFactory;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model implements HasCurrentTenantLabel
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs;

    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'is_personal',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getCurrentTenantLabel(): string
    {
        return $this->name;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->using(TeamMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function owner(): ?Model
    {
        return $this->members()->wherePivot('role', FilaTeams::ownerRole()->value)->first();
    }

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function googleDrives(): HasMany
    {
        return $this->hasMany(GoogleDrive::class);
    }

    public function cloudBackups(): HasMany
    {
        return $this->hasMany(CloudBackup::class);
    }

    public function cloudStorageConfigs(): HasMany
    {
        return $this->hasMany(CloudStorageConfig::class);
    }
}
