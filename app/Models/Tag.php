<?php

namespace App\Models;

use App\Concerns\AddTeamId;
use App\Concerns\AddUserId;
use App\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LaravelDaily\FilaTeams\Models\Team;

class Tag extends Model
{
    use AddTeamId, AddUserId, Syncable;

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'name',
        'slug',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
