<?php

namespace App\Models;

use App\Concerns\AddTeamId;
use App\Concerns\AddUserId;
use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDrive extends Model
{
    use AddTeamId, AddUserId, BelongsToTeam;

    protected $fillable = [
        'user_id',
        'team_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'root_folder_id',
        'email',
    ];

    protected $casts = [
        'access_token' => 'encrypted:array',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
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
