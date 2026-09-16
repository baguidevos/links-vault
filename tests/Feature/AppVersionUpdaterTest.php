<?php

declare(strict_types=1);

use App\Filament\Pages\DesktopSyncSettings;
use App\Livewire\AppVersionUpdater;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

test('app version updater component mounts with correct version and displays update button', function () {
    config()->set('nativephp.version', '1.0.4');

    Livewire::test(AppVersionUpdater::class)
        ->assertSet('currentVersion', '1.0.4')
        ->assertSee('v1.0.4')
        ->assertSee('Rechercher une mise à jour');
});

test('app version updater can check for updates and detect when up to date', function () {
    config()->set('nativephp.version', '1.0.4');

    Http::fake([
        'https://api.github.com/repos/baguidevos/links-vault/releases/latest' => Http::response([
            'tag_name' => 'v1.0.4',
            'html_url' => 'https://github.com/baguidevos/links-vault/releases/tag/v1.0.4',
        ], 200),
    ]);

    Livewire::test(AppVersionUpdater::class)
        ->call('checkForUpdates')
        ->assertNotified('LinksVault est à jour');
});

test('app version updater detects when a newer version is available', function () {
    config()->set('nativephp.version', '1.0.4');

    Http::fake([
        'https://api.github.com/repos/baguidevos/links-vault/releases/latest' => Http::response([
            'tag_name' => 'v1.0.5',
            'html_url' => 'https://github.com/baguidevos/links-vault/releases/tag/v1.0.5',
        ], 200),
    ]);

    Livewire::test(AppVersionUpdater::class)
        ->call('checkForUpdates')
        ->assertNotified('Nouvelle version v1.0.5 disponible !');
});

test('desktop sync settings page mounts and checks for updates successfully', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $this->actingAs($user);
    $user->teams()->attach($team->id, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    config()->set('nativephp.version', '1.0.4');

    Http::fake([
        'https://api.github.com/repos/baguidevos/links-vault/releases/latest' => Http::response([
            'tag_name' => 'v1.0.4',
            'html_url' => 'https://github.com/baguidevos/links-vault/releases/tag/v1.0.4',
        ], 200),
    ]);

    $this->actingAs($user);
    $page = new DesktopSyncSettings;
    $page->mount();

    expect($page->desktop_app_version)->toBe('1.0.4');

    $page->checkForDesktopUpdates();
});
