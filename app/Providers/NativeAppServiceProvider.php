<?php

namespace App\Providers;

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
        } catch (\Throwable $e) {
            Log::error('NativePHP auto-migration failed: '.$e->getMessage());
        }

        Menu::create(
            Menu::app(),
            Menu::file('Fichier'),
            Menu::edit('Édition'),
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

        Window::open('main')
            ->title('LinksVault')
            ->width(1360)
            ->height(860)
            ->minWidth(980)
            ->minHeight(650)
            ->rememberState()
            ->hideMenu()
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
