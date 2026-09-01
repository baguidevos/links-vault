<?php

declare(strict_types=1);

namespace App\Services\CloudBackup;

use App\Models\Category;
use App\Models\CloudBackup;
use App\Models\CloudStorageConfig;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\BookmarksExportService;
use App\Services\CloudBackup\Contracts\CloudStorageConnectorInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class VaultBackupService
{
    public function __construct(
        protected CloudStorageManager $storageManager,
        protected BookmarksExportService $exportService
    ) {}

    /**
     * Exécute une sauvegarde complète pour une équipe et un fournisseur donné (ou tous les fournisseurs actifs).
     *
     * @return array<CloudBackup>
     */
    public function backupTeam(Model|Team $team, ?string $provider = null, ?User $user = null): array
    {
        $backupData = $this->generateBackupArchive($team, $user);
        $zipContent = $backupData['content'];
        $filename = $backupData['filename'];
        $manifest = $backupData['manifest'];
        $fileSize = strlen($zipContent);

        $connectors = [];
        if ($provider) {
            $connectors[$provider] = $this->storageManager->getConnector($team, $provider, $user);
        } else {
            $connectors = $this->storageManager->getActiveConnectors($team, $user);
        }

        $results = [];

        foreach ($connectors as $provKey => $connector) {
            $backupRecord = CloudBackup::create([
                'team_id' => $team->id,
                'user_id' => $user?->id,
                'provider' => $provKey,
                'filename' => $filename,
                'file_size' => $fileSize,
                'links_count' => $manifest['stats']['links_count'] ?? 0,
                'folders_count' => $manifest['stats']['folders_count'] ?? 0,
                'tags_count' => $manifest['stats']['tags_count'] ?? 0,
                'status' => 'in_progress',
                'metadata' => [
                    'sha256' => hash('sha256', $zipContent),
                    'version' => '1.0',
                    'created_by' => $user?->name ?? 'Système',
                ],
            ]);

            try {
                $uploadResult = $connector->upload($filename, $zipContent);

                if ($uploadResult['success']) {
                    $backupRecord->update([
                        'status' => 'completed',
                        'remote_path' => $uploadResult['path'],
                        'remote_file_id' => $uploadResult['id'],
                        'error_message' => null,
                    ]);

                    // Mettre à jour la date de dernière sauvegarde
                    CloudStorageConfig::where('team_id', $team->id)
                        ->where('provider', $provKey)
                        ->update(['last_backup_at' => now()]);

                    // Appliquer la rétention
                    $this->pruneOldBackups($team, $provKey, $connector);
                } else {
                    $backupRecord->update([
                        'status' => 'failed',
                        'error_message' => $uploadResult['error'] ?? 'Échec de l\'envoi vers le cloud.',
                    ]);
                }
            } catch (Throwable $e) {
                $backupRecord->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            $results[] = $backupRecord->fresh();
        }

        return $results;
    }

    /**
     * Génère l'archive .zip contenant l'ensemble des données du Vault.
     *
     * @return array{content: string, filename: string, manifest: array<string, mixed>}
     */
    public function generateBackupArchive(Model|Team $team, ?User $user = null): array
    {
        $timestamp = now()->format('Y-m-d_His');
        $slug = Str::slug($team->name ?? 'vault');
        $filename = "linksvault_{$slug}_{$timestamp}.zip";

        $tempPath = tempnam(sys_get_temp_dir(), 'lv_backup_');
        $zip = new ZipArchive;

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le fichier d\'archive ZIP temporaire.');
        }

        // 1. Liens & Relations
        $links = Link::where('team_id', $team->id)
            ->with(['folder', 'category', 'tags'])
            ->get();

        $folders = Folder::where('team_id', $team->id)->get();
        $categories = Category::where('team_id', $team->id)->get();
        $tags = Tag::where('team_id', $team->id)->get();

        // 2. Manifest
        $manifest = [
            'app' => 'LinksVault',
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'team' => [
                'id' => $team->id,
                'name' => $team->name ?? 'Vault',
                'slug' => $team->slug ?? 'vault',
            ],
            'exported_by' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'stats' => [
                'links_count' => $links->count(),
                'folders_count' => $folders->count(),
                'categories_count' => $categories->count(),
                'tags_count' => $tags->count(),
            ],
            'files' => [
                'manifest.json',
                'links.json',
                'bookmarks.html',
                'links.csv',
                'folders.json',
                'categories.json',
                'tags.json',
            ],
        ];

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 3. Export JSON détaillé
        $linksData = $links->map(function (Link $link) {
            return [
                'id' => $link->id,
                'url' => $link->url,
                'title' => $link->title,
                'description' => $link->description,
                'objective' => $link->objective,
                'content_type' => $link->content_type instanceof \BackedEnum ? $link->content_type->value : (string) $link->content_type,
                'visibility' => $link->visibility instanceof \BackedEnum ? $link->visibility->value : (string) $link->visibility,
                'is_favorite' => $link->is_favorite,
                'is_archived' => $link->is_archived,
                'visit_count' => $link->visit_count,
                'favicon_url' => $link->favicon_url,
                'thumbnail_url' => $link->thumbnail_url,
                'metadata' => $link->getSafeMetadata(),
                'folder' => $link->folder ? ['id' => $link->folder->id, 'name' => $link->folder->name, 'slug' => $link->folder->slug] : null,
                'category' => $link->category ? ['id' => $link->category->id, 'name' => $link->category->name, 'slug' => $link->category->slug] : null,
                'tags' => ($link->relationLoaded('tags') && $link->getRelation('tags') instanceof Collection)
                    ? $link->getRelation('tags')->pluck('name')->filter()->values()->toArray()
                    : (is_string($link->tags) && filled($link->tags) ? array_values(array_filter(array_map('trim', explode(',', $link->tags)))) : []),
                'created_at' => $link->created_at?->toIso8601String(),
                'updated_at' => $link->updated_at?->toIso8601String(),
            ];
        })->toArray();

        $zip->addFromString('links.json', json_encode($linksData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 4. Netscape HTML Bookmarks
        $htmlContent = $user ? $this->exportService->exportToHtml($user, $team->id) : '';
        $zip->addFromString('bookmarks.html', $htmlContent);

        // 5. CSV Tabulaire
        $csvContent = $user ? $this->exportService->exportToCsv($user, $team->id) : '';
        $zip->addFromString('links.csv', $csvContent);

        // 6. Dossiers, Catégories, Tags
        $zip->addFromString('folders.json', json_encode($folders->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('categories.json', json_encode($categories->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('tags.json', json_encode($tags->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();

        $content = (string) file_get_contents($tempPath);
        @unlink($tempPath);

        return [
            'content' => $content,
            'filename' => $filename,
            'manifest' => $manifest,
        ];
    }

    /**
     * Purge les sauvegardes les plus anciennes selon le nombre de rétention défini.
     */
    protected function pruneOldBackups(Model|Team $team, string $provider, CloudStorageConnectorInterface $connector): void
    {
        $config = CloudStorageConfig::where('team_id', $team->id)
            ->where('provider', $provider)
            ->first();

        $retentionLimit = $config ? (int) $config->retention_count : 10;
        if ($retentionLimit <= 0) {
            $retentionLimit = 10;
        }

        $successfulBackups = CloudBackup::where('team_id', $team->id)
            ->where('provider', $provider)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        if ($successfulBackups->count() > $retentionLimit) {
            $toDelete = $successfulBackups->slice($retentionLimit);

            foreach ($toDelete as $oldBackup) {
                if (! empty($oldBackup->remote_path)) {
                    $connector->delete($oldBackup->remote_path);
                }
                $oldBackup->delete();
            }
        }
    }
}
