<?php

namespace App\Providers;

use App\Listeners\CreatePersonalTeam;
use Filament\Auth\Events\Registered as FilamentRegistered;
use Illuminate\Auth\Events\Registered as LaravelRegistered;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(FilamentRegistered::class, CreatePersonalTeam::class);
        Event::listen(LaravelRegistered::class, CreatePersonalTeam::class);

        $this->registerPlatformBladeDirectives();
    }

    /**
     * Enregistre les directives Blade conditionnelles pour les plateformes.
     */
    protected function registerPlatformBladeDirectives(): void
    {
        // @desktop : actif si l'application tourne dans NativePHP / Electron
        Blade::if('desktop', function () {
            return (bool) config('nativephp-internal.running', false)
                || request()->hasHeader('X-NativePHP-Secret')
                || str_contains((string) request()->userAgent(), 'Electron');
        });

        // @web : actif si accédé depuis un navigateur Web classique
        Blade::if('web', function () {
            return ! (
                config('nativephp-internal.running', false)
                || request()->hasHeader('X-NativePHP-Secret')
                || str_contains((string) request()->userAgent(), 'Electron')
            );
        });

        // @mobile : actif si accédé depuis un smartphone / tablette mobile
        Blade::if('mobile', function () {
            $userAgent = (string) request()->userAgent();

            return (bool) preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|iemobile|opera mini)/i', $userAgent);
        });

        // @windows : actif sur système d'exploitation Windows
        Blade::if('windows', function () {
            return PHP_OS_FAMILY === 'Windows';
        });

        // @mac : actif sur macOS
        Blade::if('mac', function () {
            return PHP_OS_FAMILY === 'Darwin';
        });
    }
}
