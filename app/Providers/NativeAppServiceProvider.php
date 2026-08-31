<?php

namespace App\Providers;

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
        Menu::new()
            ->appMenu()
            ->editMenu()
            ->viewMenu()
            ->windowMenu()
            ->register();

        MenuBar::create()
            ->icon(public_path('favicon-96x96.png'))
            ->tooltip('Links Vault')
            ->contextMenu(
                Menu::new()
                    ->link(url('/app'), 'Ouvrir LinksVault')
                    ->separator()
                    ->quit()
            );

        Window::open('main')
            ->title('Links Vault')
            ->width(1360)
            ->height(860)
            ->minWidth(980)
            ->minHeight(650)
            ->rememberState()
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
