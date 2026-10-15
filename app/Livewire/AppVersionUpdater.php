<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\DesktopHotPatchService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Native\Desktop\Facades\AutoUpdater;
use Throwable;

class AppVersionUpdater extends Component
{
    public string $currentVersion = '';

    public ?string $latestVersion = null;

    public bool $isChecking = false;

    public ?string $lastCheckedAt = null;

    public ?array $updateDownloaded = null;

    public ?array $updateAvailable = null;

    public ?array $downloadProgress = null;

    public ?string $updaterError = null;

    public ?array $patchAvailable = null;

    public bool $isDownloadingPatch = false;

    public bool $patchInstalled = false;

    public function mount(): void
    {
        $cachedPatchVersion = Cache::get('nativephp_installed_patch_version');
        $this->currentVersion = (string) ($cachedPatchVersion ?: config('nativephp.version', '1.1.4'));

        $this->updateDownloaded = Cache::get('nativephp_update_downloaded');
        $this->updateAvailable = Cache::get('nativephp_update_available');
        $this->downloadProgress = Cache::get('nativephp_download_progress');
        $this->updaterError = Cache::get('nativephp_updater_error');
        $this->patchAvailable = Cache::get('nativephp_patch_available');
        $this->patchInstalled = (bool) Cache::get('nativephp_patch_installed', false);

        $lastChecked = Cache::get('nativephp_last_checked_at');
        $this->lastCheckedAt = $lastChecked ? Carbon::parse($lastChecked)->diffForHumans() : null;
    }

    public function refreshProgress(): void
    {
        $this->downloadProgress = Cache::get('nativephp_download_progress');
        $this->updateDownloaded = Cache::get('nativephp_update_downloaded');
        $this->updateAvailable = Cache::get('nativephp_update_available');
        $this->updaterError = Cache::get('nativephp_updater_error');
        $this->patchAvailable = Cache::get('nativephp_patch_available');
        $this->patchInstalled = (bool) Cache::get('nativephp_patch_installed', false);
    }

    public function checkForUpdates(DesktopHotPatchService $patchService): void
    {
        $this->isChecking = true;
        Cache::put('nativephp_checking_updates', true, now()->addMinutes(2));
        Cache::forget('nativephp_updater_error');

        $isDesktop = (bool) config('nativephp-internal.running', false)
            || request()->hasHeader('X-NativePHP-Secret')
            || str_contains((string) request()->userAgent(), 'Electron');

        // 1. Recherche prioritaire d'un Hot-Patch différentiel léger
        $patch = $patchService->checkForPatch($this->currentVersion);
        if ($patch) {
            $this->patchAvailable = $patch;
            $this->latestVersion = $patch['version'];
            Cache::put('nativephp_patch_available', $patch, now()->addHours(6));

            $now = now()->toIso8601String();
            Cache::put('nativephp_last_checked_at', $now, now()->addDays(1));
            $this->lastCheckedAt = Carbon::parse($now)->diffForHumans();
            $this->isChecking = false;

            Notification::make()
                ->title("Mise à jour rapide v{$patch['version']} disponible !")
                ->body("Un patch léger ({$patch['patch_size_human']}) ne contenant que les fichiers modifiés est prêt à être installé.")
                ->info()
                ->send();

            return;
        }

        // 2. Si on est dans l'environnement Desktop empaqueté, déclencher l'AutoUpdater natif
        if ($isDesktop && class_exists(AutoUpdater::class)) {
            try {
                AutoUpdater::checkForUpdates();
            } catch (Throwable) {
                // Non-bloquant
            }
        }

        // 3. Vérification auprès de GitHub Releases pour détection standard (installeur complet)
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'LinksVault-App',
                'Accept' => 'application/vnd.github+json',
            ])->timeout(5)->get('https://api.github.com/repos/baguidevos/links-vault/releases/latest');

            $now = now()->toIso8601String();
            Cache::put('nativephp_last_checked_at', $now, now()->addDays(1));
            $this->lastCheckedAt = Carbon::parse($now)->diffForHumans();

            if ($response->successful()) {
                $releaseData = $response->json();
                $remoteTag = ltrim((string) ($releaseData['tag_name'] ?? ''), 'v');
                $localVersion = ltrim($this->currentVersion, 'v');
                $this->latestVersion = $remoteTag;

                if (version_compare($remoteTag, $localVersion, '>')) {
                    Cache::put('nativephp_update_available', [
                        'version' => $remoteTag,
                        'detected_at' => $now,
                        'html_url' => $releaseData['html_url'] ?? null,
                    ], now()->addHours(6));

                    Notification::make()
                        ->title("Nouvelle version v{$remoteTag} disponible !")
                        ->body('Une mise à jour plus récente est disponible sur GitHub Releases.')
                        ->info()
                        ->send();
                } else {
                    Notification::make()
                        ->title('LinksVault est à jour')
                        ->body("Vous disposez déjà de la version la plus récente (v{$this->currentVersion}).")
                        ->success()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Recherche de mise à jour')
                    ->body("Version actuelle : v{$this->currentVersion}.")
                    ->info()
                    ->send();
            }
        } catch (Throwable) {
            Notification::make()
                ->title('Vérification effectuée')
                ->body("Version actuelle : v{$this->currentVersion}.")
                ->info()
                ->send();
        } finally {
            $this->isChecking = false;
            $this->mount();
        }
    }

    public function downloadAndApplyPatch(DesktopHotPatchService $patchService): void
    {
        if (! $this->patchAvailable) {
            return;
        }

        $this->isDownloadingPatch = true;

        try {
            $patchUrl = $this->patchAvailable['patch_url'];
            $version = $this->patchAvailable['version'];

            // Téléchargement avec suivi de progression
            $zipPath = $patchService->downloadPatch($patchUrl, $version);

            // Application du patch
            $result = $patchService->applyPatch($zipPath);

            $this->currentVersion = $result['version'];
            $this->patchInstalled = true;
            $this->patchAvailable = null;
            Cache::put('nativephp_patch_installed', true, now()->addDays(7));
            Cache::forget('nativephp_patch_available');

            Notification::make()
                ->title("Patch v{$result['version']} installé avec succès !")
                ->body("{$result['files_count']} fichiers mis à jour. Redémarrez l'application pour appliquer les changements.")
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Erreur lors de l’application du patch')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isDownloadingPatch = false;
            $this->mount();
        }
    }

    public function relaunchApp(DesktopHotPatchService $patchService): void
    {
        Cache::forget('nativephp_patch_installed');
        $patchService->relaunch();
    }

    public function installUpdate(): void
    {
        Cache::forget('nativephp_update_downloaded');

        if (class_exists(AutoUpdater::class)) {
            try {
                AutoUpdater::quitAndInstall();
            } catch (Throwable $e) {
                Notification::make()
                    ->title("Erreur lors de l'installation")
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }

    public function render(): View
    {
        return view('livewire.app-version-updater');
    }
}
