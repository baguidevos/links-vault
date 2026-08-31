<?php

declare(strict_types=1);

namespace App\Services\CloudBackup\Connectors;

use App\Services\CloudBackup\Contracts\CloudStorageConnectorInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

class DropboxConnector implements CloudStorageConnectorInterface
{
    /**
     * @param  array{
     *     access_token: string,
     *     folder?: string
     * }  $credentials
     */
    public function __construct(
        protected int $teamId,
        protected array $credentials
    ) {}

    protected function getAccessToken(): ?string
    {
        return $this->credentials['access_token'] ?? null;
    }

    public function upload(string $remoteFilename, string $fileContent): array
    {
        try {
            $token = $this->getAccessToken();
            if (empty($token)) {
                return [
                    'success' => false,
                    'path' => '',
                    'id' => null,
                    'error' => 'Jeton d\'accès Dropbox manquant.',
                ];
            }

            $folder = ! empty($this->credentials['folder']) ? '/'.trim($this->credentials['folder'], '/') : '/LinksVault_Backups';
            $path = "{$folder}/team-{$this->teamId}/{$remoteFilename}";

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $path,
                        'mode' => 'overwrite',
                        'autorename' => false,
                        'mute' => false,
                    ]),
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($fileContent, 'application/octet-stream')
                ->post('https://content.dropboxapi.com/2/files/upload');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'path' => $path,
                    'id' => $data['id'] ?? $path,
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'path' => '',
                'id' => null,
                'error' => 'Erreur Dropbox : '.$response->body(),
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
            $token = $this->getAccessToken();
            if (empty($token)) {
                return null;
            }

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Dropbox-API-Arg' => json_encode(['path' => $remotePath]),
                ])
                ->post('https://content.dropboxapi.com/2/files/download');

            if ($response->successful()) {
                return $response->body();
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(string $remotePath): bool
    {
        try {
            $token = $this->getAccessToken();
            if (empty($token)) {
                return false;
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.dropboxapi.com/2/files/delete_v2', [
                    'path' => $remotePath,
                ]);

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function testConnection(): array
    {
        try {
            $token = $this->getAccessToken();
            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => 'Veuillez saisir votre jeton d\'accès Dropbox.',
                ];
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                ])
                ->post('https://api.dropboxapi.com/2/users/get_current_account');

            if ($response->successful()) {
                $data = $response->json();
                $name = $data['name']['display_name'] ?? ($data['email'] ?? 'Utilisateur Dropbox');

                return [
                    'success' => true,
                    'message' => "Connexion réussie à Dropbox ({$name}) !",
                ];
            }

            return [
                'success' => false,
                'message' => 'Jeton Dropbox invalide ou expiré : '.$response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erreur Dropbox : '.$e->getMessage(),
            ];
        }
    }

    public function getProviderKey(): string
    {
        return 'dropbox';
    }
}
