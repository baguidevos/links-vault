<?php

declare(strict_types=1);

namespace App\Services\CloudBackup\Connectors;

use App\Services\CloudBackup\Contracts\CloudStorageConnectorInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

class S3CompatibleConnector implements CloudStorageConnectorInterface
{
    protected ?Filesystem $disk = null;

    /**
     * @param  array{
     *     key: string,
     *     secret: string,
     *     bucket: string,
     *     region?: string,
     *     endpoint?: string,
     *     use_path_style_endpoint?: bool,
     *     prefix?: string
     * }  $credentials
     */
    public function __construct(
        protected int $teamId,
        protected array $credentials
    ) {}

    protected function getDisk(): Filesystem
    {
        if ($this->disk === null) {
            $config = [
                'driver' => 's3',
                'key' => $this->credentials['key'] ?? '',
                'secret' => $this->credentials['secret'] ?? '',
                'region' => ! empty($this->credentials['region']) ? $this->credentials['region'] : 'auto',
                'bucket' => $this->credentials['bucket'] ?? '',
                'use_path_style_endpoint' => ! empty($this->credentials['use_path_style_endpoint']),
                'throw' => true,
            ];

            if (! empty($this->credentials['endpoint'])) {
                $config['endpoint'] = $this->credentials['endpoint'];
            }

            $this->disk = Storage::build($config);
        }

        return $this->disk;
    }

    public function upload(string $remoteFilename, string $fileContent): array
    {
        try {
            $prefix = ! empty($this->credentials['prefix']) ? rtrim($this->credentials['prefix'], '/').'/' : 'linksvault-backups/';
            $path = "{$prefix}team-{$this->teamId}/{$remoteFilename}";

            $this->getDisk()->put($path, $fileContent);

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
            if ($this->getDisk()->exists($remotePath)) {
                return $this->getDisk()->get($remotePath);
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(string $remotePath): bool
    {
        try {
            return $this->getDisk()->delete($remotePath);
        } catch (Throwable) {
            return false;
        }
    }

    public function testConnection(): array
    {
        try {
            $prefix = ! empty($this->credentials['prefix']) ? rtrim($this->credentials['prefix'], '/').'/' : 'linksvault-backups/';
            $testPath = "{$prefix}team-{$this->teamId}/.connection_test";

            $this->getDisk()->put($testPath, 'connection_ok');
            $this->getDisk()->delete($testPath);

            return [
                'success' => true,
                'message' => 'Connexion au bucket S3 / R2 réussie avec succès !',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Échec de connexion S3 / R2 : '.$e->getMessage(),
            ];
        }
    }

    public function getProviderKey(): string
    {
        return 's3';
    }
}
