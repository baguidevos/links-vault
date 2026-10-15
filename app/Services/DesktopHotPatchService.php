<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\App;
use RuntimeException;
use Throwable;
use ZipArchive;

class DesktopHotPatchService
{
    /**
     * Allowed top-level directories and files that can be updated via hot-patch.
     * Prevents any modification to sensitive system files (.env, git, vendor binaries, etc.).
     */
    protected const ALLOWED_PREFIXES = [
        'app/',
        'config/',
        'database/migrations/',
        'database/seeders/',
        'public/build/',
        'resources/',
        'routes/',
        'nativephp/electron/electron-plugin/dist/',
    ];

    /**
     * Check if a hot patch is available for the given current version from GitHub Releases.
     *
     * @return array{
     *     version: string,
     *     patch_url: string,
     *     patch_size: int,
     *     patch_size_human: string,
     *     html_url: ?string,
     *     is_hot_patch: bool
     * }|null
     */
    public function checkForPatch(string $currentVersion): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'LinksVault-Desktop-App',
                'Accept' => 'application/vnd.github+json',
            ])->timeout(8)->get('https://api.github.com/repos/baguidevos/links-vault/releases/latest');

            if (! $response->successful()) {
                return null;
            }

            $releaseData = $response->json();
            $remoteTag = ltrim((string) ($releaseData['tag_name'] ?? ''), 'v');
            $localVersion = ltrim($currentVersion, 'v');

            if (! version_compare($remoteTag, $localVersion, '>')) {
                return null;
            }

            $assets = $releaseData['assets'] ?? [];
            if (! is_array($assets) || empty($assets)) {
                return null;
            }

            // Look for targeted patch: patch-{localVersion}-to-{remoteTag}.zip, or general: patch-{remoteTag}.zip, or patch.zip
            $targetAsset = null;
            $candidateNames = [
                "patch-{$localVersion}-to-{$remoteTag}.zip",
                "patch-{$remoteTag}.zip",
                'patch.zip',
            ];

            foreach ($candidateNames as $candidate) {
                foreach ($assets as $asset) {
                    if (strcasecmp((string) ($asset['name'] ?? ''), $candidate) === 0) {
                        $targetAsset = $asset;
                        break 2;
                    }
                }
            }

            if (! $targetAsset) {
                return null;
            }

            $size = (int) ($targetAsset['size'] ?? 0);
            $sizeHuman = $this->formatBytes($size);

            return [
                'version' => $remoteTag,
                'patch_url' => $targetAsset['browser_download_url'],
                'patch_size' => $size,
                'patch_size_human' => $sizeHuman,
                'html_url' => $releaseData['html_url'] ?? null,
                'is_hot_patch' => true,
            ];
        } catch (Throwable $e) {
            Log::warning('DesktopHotPatchService: failed checking for patch: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Download the patch archive and track progress in Cache.
     */
    public function downloadPatch(string $downloadUrl, string $targetVersion): string
    {
        $tempDir = storage_path('app/patch_temp');
        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $destPath = $tempDir.'/patch-'.$targetVersion.'.zip';
        if (File::exists($destPath)) {
            File::delete($destPath);
        }

        Cache::put('nativephp_download_progress', [
            'percent' => 0,
            'transferred' => 0,
            'total' => 0,
            'bytesPerSecond' => 0,
            'humanTransferred' => '0 Ko',
            'humanTotal' => 'Calcul...',
            'humanSpeed' => '0 Ko/s',
            'updated_at' => now()->timestamp,
        ], now()->addMinutes(5));

        $fp = fopen($destPath, 'w+');
        if (! $fp) {
            throw new RuntimeException("Impossible de créer le fichier temporaire de patch : {$destPath}");
        }

        $lastUpdate = microtime(true);
        $lastBytes = 0;

        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinksVault-Desktop-App');
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $totalBytes, $downloadedBytes) use (&$lastUpdate, &$lastBytes) {
            if ($totalBytes > 0) {
                $now = microtime(true);
                $elapsed = $now - $lastUpdate;

                // Update cache every 0.3s or upon completion
                if ($elapsed >= 0.3 || $downloadedBytes >= $totalBytes) {
                    $speed = $elapsed > 0 ? ($downloadedBytes - $lastBytes) / $elapsed : 0;
                    $percent = (int) min(100, round(($downloadedBytes / $totalBytes) * 100));

                    Cache::put('nativephp_download_progress', [
                        'percent' => $percent,
                        'transferred' => (int) $downloadedBytes,
                        'total' => (int) $totalBytes,
                        'bytesPerSecond' => (int) $speed,
                        'humanTransferred' => $this->formatBytes((int) $downloadedBytes),
                        'humanTotal' => $this->formatBytes((int) $totalBytes),
                        'humanSpeed' => $this->formatBytes((int) $speed).'/s',
                        'updated_at' => now()->timestamp,
                    ], now()->addMinutes(5));

                    $lastUpdate = $now;
                    $lastBytes = $downloadedBytes;
                }
            }
        });

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (! $success || $httpCode >= 400) {
            if (File::exists($destPath)) {
                File::delete($destPath);
            }
            Cache::forget('nativephp_download_progress');
            throw new RuntimeException("Erreur lors du téléchargement du patch (Code {$httpCode}): {$error}");
        }

        Cache::forget('nativephp_download_progress');

        Cache::put('nativephp_patch_downloaded', [
            'version' => $targetVersion,
            'file_path' => $destPath,
            'downloaded_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        return $destPath;
    }

    /**
     * Apply the downloaded patch archive.
     *
     * @return array{
     *     success: bool,
     *     version: string,
     *     files_count: int,
     *     message: string
     * }
     */
    public function applyPatch(string $zipPath): array
    {
        if (! File::exists($zipPath)) {
            throw new RuntimeException("Le fichier de patch est introuvable : {$zipPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Impossible d’ouvrir l’archive zip de patch.');
        }

        $manifestContent = $zip->getFromName('manifest.json');
        if (! $manifestContent) {
            $zip->close();
            throw new RuntimeException("Le fichier de manifest 'manifest.json' est manquant dans l’archive.");
        }

        $manifest = json_decode($manifestContent, true);
        if (! is_array($manifest) || empty($manifest['to_version'])) {
            $zip->close();
            throw new RuntimeException('Format de manifest.json invalide.');
        }

        $targetVersion = (string) $manifest['to_version'];
        $filesToUpdate = $manifest['files'] ?? [];
        $filesToDelete = $manifest['deleted_files'] ?? [];

        // Validate paths security
        foreach ($filesToUpdate as $fileInfo) {
            $relPath = is_array($fileInfo) ? ($fileInfo['path'] ?? '') : (string) $fileInfo;
            $this->validateSafePath($relPath);
        }

        foreach ($filesToDelete as $relPath) {
            $this->validateSafePath((string) $relPath);
        }

        $backupDir = storage_path('app/patch_backup/'.now()->format('Ymd_His'));
        File::makeDirectory($backupDir, 0755, true);

        // Extract and copy files
        $appliedCount = 0;
        foreach ($filesToUpdate as $fileInfo) {
            $relPath = is_array($fileInfo) ? ($fileInfo['path'] ?? '') : (string) $fileInfo;
            $content = $zip->getFromName($relPath);

            if ($content === false) {
                continue;
            }

            $destination = base_path($relPath);
            $destinationDir = dirname($destination);

            // Backup existing file if present
            if (File::exists($destination)) {
                $backupDest = $backupDir.'/'.$relPath;
                File::makeDirectory(dirname($backupDest), 0755, true);
                File::copy($destination, $backupDest);
            }

            if (! File::isDirectory($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true);
            }

            File::put($destination, $content);
            $appliedCount++;
        }

        // Delete removed files
        foreach ($filesToDelete as $relPath) {
            $target = base_path((string) $relPath);
            if (File::exists($target)) {
                File::delete($target);
            }
        }

        $zip->close();

        // Run migrations if indicated
        if (! empty($manifest['has_migrations'])) {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $e) {
                Log::warning('DesktopHotPatchService: migration failed after patch: '.$e->getMessage());
            }
        }

        // Clear views, routes and config caches
        try {
            Artisan::call('optimize:clear');
        } catch (Throwable) {
            // Ignore if cache clear fails
        }

        // Update runtime version
        config(['nativephp.version' => $targetVersion]);
        Cache::put('nativephp_installed_patch_version', $targetVersion, now()->addDays(30));
        Cache::put('nativephp_update_downloaded', [
            'version' => $targetVersion,
            'is_hot_patch' => true,
            'applied_at' => now()->toIso8601String(),
        ], now()->addDays(7));

        Cache::forget('nativephp_update_available');
        Cache::forget('nativephp_patch_downloaded');
        Cache::forget('nativephp_download_progress');

        // Cleanup patch zip
        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        return [
            'success' => true,
            'version' => $targetVersion,
            'files_count' => $appliedCount,
            'message' => "Le patch v{$targetVersion} a été appliqué avec succès ({$appliedCount} fichiers mis à jour).",
        ];
    }

    /**
     * Relaunch the desktop application.
     */
    public function relaunch(): void
    {
        if (class_exists(App::class)) {
            try {
                App::relaunch();

                return;
            } catch (Throwable $e) {
                Log::warning('App::relaunch failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Ensure the path does not traverse directories and is within allowed boundaries.
     */
    protected function validateSafePath(string $path): void
    {
        $normalized = str_replace('\\', '/', trim($path));

        if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            throw new RuntimeException("Chemin de fichier dangereux détecté : {$path}");
        }

        foreach (self::ALLOWED_PREFIXES as $allowed) {
            if (str_starts_with($normalized, $allowed)) {
                return;
            }
        }

        throw new RuntimeException("Le chemin '{$path}' n'est pas autorisé pour les mises à jour hot-patch.");
    }

    /**
     * Helper to format bytes into human readable string.
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' Mo';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' Ko';
        }

        return $bytes.' octets';
    }
}
