<?php

declare(strict_types=1);

namespace App\Services\CloudBackup\Connectors;

use App\Services\CloudBackup\Contracts\CloudStorageConnectorInterface;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LocalDiskConnector implements CloudStorageConnectorInterface
{
    public function __construct(
        protected int $teamId,
        protected string $disk = 'local'
    ) {}

    public function upload(string $remoteFilename, string $fileContent): array
    {
        try {
            $path = "vault-backups/{$this->teamId}/{$remoteFilename}";
            Storage::disk($this->disk)->put($path, $fileContent);

            return [
                'success' => true,
                'path' => $path,
                'id' => $path,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'path' => '',
                'id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function download(string $remotePath): ?string
    {
        try {
            if (Storage::disk($this->disk)->exists($remotePath)) {
                return Storage::disk($this->disk)->get($remotePath);
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(string $remotePath): bool
    {
        try {
            return Storage::disk($this->disk)->delete($remotePath);
        } catch (Throwable) {
            return false;
        }
    }

    public function testConnection(): array
    {
        try {
            $testPath = "vault-backups/{$this->teamId}/.connection_test";
            Storage::disk($this->disk)->put($testPath, 'ok');
            Storage::disk($this->disk)->delete($testPath);

            return [
                'success' => true,
                'message' => 'Connexion au stockage local réussie.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erreur de stockage local : '.$e->getMessage(),
            ];
        }
    }

    public function getProviderKey(): string
    {
        return 'local';
    }
}
