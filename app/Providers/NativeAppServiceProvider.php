<?php

namespace App\Providers;

use App\Jobs\SyncWithCloudJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Nafiswatsiq\Subbase\Models\Plan as ModelsPlan;
use Native\Desktop\Contracts\ProvidesPhpIni;
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
        // SOLUTION 1 : Barre de menus Desktop native (ACTIVE)
        // Menu 'Navigation' avec les actions Page précédente / suivante / Actualiser.
        // Toujours présent et utilisable, même sur les écrans d'erreur 500 / exceptions.
        // =========================================================================
        Menu::create(
            Menu::app(),
            Menu::file('Fichier'),
            Menu::reload('Actualiser (F5)'),
            Menu::window('Fenêtre')
        );

        MenuBar::create()
            ->icon(public_path('icon.png'))
            ->tooltip('LinksVault')
            ->onlyShowContextMenu()
            ->withContextMenu(
                Menu::make(
                    Menu::link(url('/app'), 'Ouvrir LinksVault'),
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
}
