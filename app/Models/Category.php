<?php

namespace App\Models;

use App\Concerns\AddTeamId;
use App\Concerns\AddUserId;
use App\Concerns\BelongsToTeam;
use App\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use AddTeamId, AddUserId, BelongsToTeam, Syncable;

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'name',
        'slug',
        'color',
        'icon',
        'description',
        'sort_order',
    ];

    protected $casts = [
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

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
