<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncSetting extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'server_url',
        'api_token',
        'last_synced_at',
        'sync_status',
        'auto_sync_enabled',
        'last_error',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'last_synced_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->server_url) && ! empty($this->api_token);
    }
}
