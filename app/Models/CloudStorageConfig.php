<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudStorageConfig extends Model
{
    use BelongsToTeam, HasFactory;

    protected $fillable = [
        'team_id',
        'provider',
        'is_active',
        'auto_backup_enabled',
        'frequency',
        'retention_count',
        'credentials',
        'last_backup_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_backup_enabled' => 'boolean',
        'retention_count' => 'integer',
        'credentials' => 'encrypted:array',
        'last_backup_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
