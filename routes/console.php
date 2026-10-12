<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vault:backup-cloud')
    ->dailyAt('03:00')
    ->description('Sauvegarde automatique des espaces LinksVault vers les stockages cloud configurés');

Schedule::command('subscriptions:check-expirations')
    ->dailyAt('00:30')
    ->description('Vérifier et rétrograder les abonnements expirés vers le plan gratuit');

Schedule::command('links:check-health --limit=200')
    ->dailyAt('04:00')
    ->description('Scan planifié de détection des liens morts');
