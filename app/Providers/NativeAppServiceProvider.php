<?php

namespace App\Providers;

use App\Jobs\SyncWithCloudJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Nafiswatsiq\Subbase\Models\Plan as ModelsPlan;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Events\AutoUpdater\CheckingForUpdate;
use Native\Desktop\Events\AutoUpdater\Error as AutoUpdaterError;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Events\AutoUpdater\UpdateDownloaded;
use Native\Desktop\Events\AutoUpdater\UpdateNotAvailable;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // Enregistrer les écouteurs d'événements pour le cycle de vie de l'AutoUpdater
        $this->registerAutoUpdateListeners();

        // Exécution automatique des migrations en attente sur l'application Desktop
        try {
            Artisan::call('migrate', ['--force' => true]);

            if (ModelsPlan::count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\PlanSeeder',
                    '--force' => true,
                ]);
            }

            // Déclencher la synchronisation en arrière-plan au démarrage du Desktop
            SyncWithCloudJob::dispatch();
        } catch (\Throwable $e) {
            Log::error('NativePHP auto-migration/sync failed: '.$e->getMessage());
        }

        // =========================================================================
        // Barre de menus Desktop native
        // =========================================================================
        Menu::create(
            Menu::app(),
            Menu::file('Fichier'),
            Menu::reload('Actualiser (F5)'),
            Menu::window('Fenêtre'),
            Menu::make(
                Menu::link(url('/app/settings/sync'), 'Mises à jour & Synchronisation...'),
                Menu::separator(),
                Menu::about('À propos de LinksVault')
            )->label('Aide')
        );

        MenuBar::create()
            ->icon(public_path('icon.png'))
            ->tooltip('LinksVault')
            ->onlyShowContextMenu()
            ->withContextMenu(
                Menu::make(
                    Menu::link(url('/app'), 'Ouvrir LinksVault'),
                    Menu::link(url('/app/settings/sync'), 'Mises à jour & Synchronisation'),
                    Menu::separator(),
                    Menu::quit('Quitter LinksVault')
                )
            );

        // --- SOLUTION 1 (ACTIVE) : Fenêtre avec barre de menus native apparente ---
        // Window::open('main')
        //     ->title('LinksVault')
        //     ->width(1360)
        //     ->height(860)
        //     ->minWidth(980)
        //     ->minHeight(650)
        //     ->rememberState()
        //     ->hideMenu() // Désactivé pour la Solution 1 : permet d'afficher la barre de menus avec 'Navigation'
        //     ->showDevTools(false)
        //     ->url(url('/app'));

        // =========================================================================
        // SOLUTION 2 : Barre de titre personnalisée HTML/CSS intégrée (COMMENTÉE)
        // Pour tester la Solution 2 :
        // 1. Commentez le bloc Window::open('main') de la Solution 1 ci-dessus
        // 2. Décommentez le bloc Window::open('main') ci-dessous
        // 3. Décommentez le renderHook(PanelsRenderHook::BODY_START, ...) dans AppPanelProvider.php
        // =========================================================================

        Window::open('main')
            ->title('LinksVault')
            ->width(1360)
            ->height(860)
            ->minWidth(980)
            ->minHeight(650)
            ->rememberState()
            // ->titleBarHidden() // Masque la barre native Windows pour laisser place à la barre personnalisée
            ->hideMenu()       // Masque la barre de menus standard
            ->showDevTools(false)
            ->url(url('/app'));

    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'display_errors' => '1',
            'error_reporting' => 'E_ALL',
        ];
    }

    /**
     * Enregistre les écouteurs d'événements pour l'AutoUpdater NativePHP.
     */
    protected function registerAutoUpdateListeners(): void
    {
        Event::listen(UpdateDownloaded::class, function (UpdateDownloaded $event) {
            Log::info('NativePHP Desktop update downloaded', [
                'version' => $event->version,
                'releaseDate' => $event->releaseDate ?? null,
            ]);
            cache()->put('nativephp_update_downloaded', [
                'version' => $event->version,
                'releaseNotes' => $event->releaseNotes ?? null,
                'downloaded_at' => now()->toIso8601String(),
            ], now()->addDays(7));
            cache()->forget('nativephp_checking_updates');
        });

        Event::listen(UpdateAvailable::class, function (UpdateAvailable $event) {
            Log::info('NativePHP Desktop update available', ['version' => $event->version]);
            cache()->put('nativephp_update_available', [
                'version' => $event->version,
                'detected_at' => now()->toIso8601String(),
            ], now()->addHours(6));
            cache()->forget('nativephp_checking_updates');
        });

        Event::listen(UpdateNotAvailable::class, function () {
            Log::info('NativePHP Desktop is up to date.');
            cache()->forget('nativephp_update_available');
            cache()->forget('nativephp_checking_updates');
            cache()->put('nativephp_last_checked_at', now()->toIso8601String(), now()->addDays(1));
        });

        Event::listen(CheckingForUpdate::class, function () {
            Log::info('NativePHP Desktop checking for updates...');
            cache()->put('nativephp_checking_updates', true, now()->addMinutes(5));
        });

        Event::listen(AutoUpdaterError::class, function (AutoUpdaterError $event) {
            Log::warning('NativePHP Desktop updater error: '.$event->error);
            cache()->forget('nativephp_checking_updates');
            cache()->put('nativephp_updater_error', $event->error, now()->addHours(1));
        });
    }
}
