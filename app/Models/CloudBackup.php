<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudBackup extends Model
{
    use BelongsToTeam, HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'provider',
        'filename',
        'file_size',
        'remote_path',
        'remote_file_id',
        'links_count',
        'folders_count',
        'tags_count',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'links_count' => 'integer',
        'folders_count' => 'integer',
        'tags_count' => 'integer',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get human-readable file size (e.g., 2.45 MB).
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }

    /**
     * Get badge color by provider.
     */
    public function getProviderColorAttribute(): string
    {
        return match ($this->provider) {
            'google_drive' => 'warning',
            's3' => 'info',
            'dropbox' => 'primary',
            'local' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get human label by provider.
     */
    public function getProviderLabelAttribute(): string
    {
        return match ($this->provider) {
            'google_drive' => 'Google Drive',
            's3' => 'S3 / R2 / MinIO',
            'dropbox' => 'Dropbox',
            'local' => 'Stockage Local',
            default => ucfirst((string) $this->provider),
        };
    }
}
