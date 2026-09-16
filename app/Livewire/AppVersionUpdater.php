<?php

declare(strict_types=1);

namespace App\Livewire;

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

    public ?string $updaterError = null;

    public function mount(): void
    {
        $this->currentVersion = (string) config('nativephp.version', '1.0.4');
        $this->updateDownloaded = Cache::get('nativephp_update_downloaded');
        $this->updateAvailable = Cache::get('nativephp_update_available');
        $this->updaterError = Cache::get('nativephp_updater_error');

        $lastChecked = Cache::get('nativephp_last_checked_at');
        $this->lastCheckedAt = $lastChecked ? Carbon::parse($lastChecked)->diffForHumans() : null;
    }

    public function checkForUpdates(): void
    {
        $this->isChecking = true;
        Cache::put('nativephp_checking_updates', true, now()->addMinutes(2));
        Cache::forget('nativephp_updater_error');

        $isDesktop = (bool) config('nativephp-internal.running', false)
            || request()->hasHeader('X-NativePHP-Secret')
            || str_contains((string) request()->userAgent(), 'Electron');

        // 1. Si on est dans l'environnement Desktop empaqueté, déclencher l'AutoUpdater natif
        if ($isDesktop && class_exists(AutoUpdater::class)) {
            try {
                AutoUpdater::checkForUpdates();
            } catch (Throwable $e) {
                // Non-bloquant
            }
        }

        // 2. Vérification auprès de GitHub Releases pour retour instantané
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
        } catch (Throwable $e) {
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
