<?php

namespace App\Providers;

use App\Listeners\CreatePersonalTeam;
use Filament\Auth\Events\Registered as FilamentRegistered;
use Illuminate\Auth\Events\Registered as LaravelRegistered;
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
    }
}
