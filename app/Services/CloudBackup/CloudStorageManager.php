<?php

declare(strict_types=1);

namespace App\Services\CloudBackup;

use App\Models\CloudStorageConfig;
use App\Models\Team;
use App\Models\User;
use App\Services\CloudBackup\Connectors\DropboxConnector;
use App\Services\CloudBackup\Connectors\GoogleDriveConnector;
use App\Services\CloudBackup\Connectors\LocalDiskConnector;
use App\Services\CloudBackup\Connectors\S3CompatibleConnector;
use App\Services\CloudBackup\Contracts\CloudStorageConnectorInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CloudStorageManager
{
    /**
     * Get connector instance for a specific team and provider.
     */
    public function getConnector(int|Model|Team $team, string $provider, ?User $user = null): CloudStorageConnectorInterface
    {
        $teamId = $team instanceof Model ? (int) $team->getKey() : (int) $team;

        $config = CloudStorageConfig::where('team_id', $teamId)
            ->where('provider', $provider)
            ->first();

        $credentials = $config?->credentials ?? [];

        return match ($provider) {
            'local' => new LocalDiskConnector($teamId),
            's3' => new S3CompatibleConnector($teamId, $credentials),
            'google_drive' => new GoogleDriveConnector($teamId, $user, $credentials),
            'dropbox' => new DropboxConnector($teamId, $credentials),
            default => throw new InvalidArgumentException("Fournisseur de stockage non supporté : {$provider}"),
        };
    }

    /**
     * Get all active connectors configured for a team.
     *
     * @return array<string, CloudStorageConnectorInterface>
     */
    public function getActiveConnectors(int|Model|Team $team, ?User $user = null): array
    {
        $teamId = $team instanceof Model ? (int) $team->getKey() : (int) $team;

        $activeConfigs = CloudStorageConfig::where('team_id', $teamId)
            ->where('is_active', true)
            ->get();

        $connectors = [];

        foreach ($activeConfigs as $config) {
            try {
                $connectors[$config->provider] = $this->getConnector($teamId, $config->provider, $user);
            } catch (\Throwable) {
                // Ignore connector instantiation errors
            }
        }

        // Always provide local disk connector as guaranteed backup fallback
        if (empty($connectors) || ! isset($connectors['local'])) {
            $connectors['local'] = new LocalDiskConnector($teamId);
        }

        return $connectors;
    }
}
