<?php

declare(strict_types=1);

namespace App\Services\CloudBackup\Connectors;

use App\Models\GoogleDrive as GoogleDriveModel;
use App\Models\User;
use App\Services\CloudBackup\Contracts\CloudStorageConnectorInterface;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Throwable;

class GoogleDriveConnector implements CloudStorageConnectorInterface
{
    public function __construct(
        protected int $teamId,
        protected ?User $user = null,
        protected array $credentials = []
    ) {}

    protected function getClient(): ?Client
    {
        $clientId = $this->credentials['client_id'] ?? config('services.google.client_id', '');
        $clientSecret = $this->credentials['client_secret'] ?? config('services.google.client_secret', '');
        $refreshToken = $this->credentials['refresh_token'] ?? null;

        $driveModel = null;
        if (! $refreshToken) {
            $driveModel = GoogleDriveModel::where('team_id', $this->teamId)
                ->when($this->user, fn ($q) => $q->orWhere('user_id', $this->user->id))
                ->first();

            $refreshToken = $driveModel?->refresh_token;
        }

        if (empty($clientId) || (empty($refreshToken) && (! $driveModel || empty($driveModel->access_token)))) {
            return null;
        }

        $client = new Client;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);

        if ($refreshToken) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (! isset($newToken['error'])) {
                $client->setAccessToken($newToken);
                if ($driveModel) {
                    $driveModel->update([
                        'access_token' => $newToken,
                        'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                    ]);
                }
            }
        } elseif ($driveModel?->access_token) {
            $client->setAccessToken($driveModel->access_token);
        }

        return $client;
    }

    public function upload(string $remoteFilename, string $fileContent): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return [
                    'success' => false,
                    'path' => '',
                    'id' => null,
                    'error' => 'Compte Google Drive non connecté.',
                ];
            }

            $service = new Drive($client);

            // Obtenir ou créer le dossier racine LinksVault_Backups
            $folderId = $this->getOrCreateBackupsFolder($service);

            $fileMetadata = new DriveFile([
                'name' => $remoteFilename,
                'parents' => [$folderId],
            ]);

            $createdFile = $service->files->create($fileMetadata, [
                'data' => $fileContent,
                'mimeType' => 'application/zip',
                'uploadType' => 'multipart',
                'fields' => 'id, name, webViewLink, size',
            ]);

            return [
                'success' => true,
                'path' => $remoteFilename,
                'id' => $createdFile->getId(),
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
            $client = $this->getClient();
            if (! $client) {
                return null;
            }

            $service = new Drive($client);
            $response = $service->files->get($remotePath, ['alt' => 'media']);

            return (string) $response->getBody();
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(string $remotePath): bool
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return false;
            }

            $service = new Drive($client);
            $service->files->delete($remotePath);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function testConnection(): array
    {
        try {
            $client = $this->getClient();
            if (! $client) {
                return [
                    'success' => false,
                    'message' => 'Veuillez associer votre compte Google Drive.',
                ];
            }

            $service = new Drive($client);
            $about = $service->about->get(['fields' => 'user(displayName, emailAddress)']);

            return [
                'success' => true,
                'message' => 'Connecté à Google Drive en tant que '.$about->getUser()?->getEmailAddress(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erreur Google Drive : '.$e->getMessage(),
            ];
        }
    }

    public function getProviderKey(): string
    {
        return 'google_drive';
    }

    protected function getOrCreateBackupsFolder(Drive $service): string
    {
        $folderName = $this->credentials['folder_name'] ?? 'LinksVault_Backups';
        $query = "mimeType='application/vnd.google-apps.folder' and name='{$folderName}' and trashed=false";

        $results = $service->files->listFiles([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)',
        ]);

        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }

        $folderMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $folder = $service->files->create($folderMetadata, ['fields' => 'id']);

        return $folder->getId();
    }
}
