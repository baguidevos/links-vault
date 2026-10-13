<?php

use App\Filament\Admin\Pages\ManageEcosystemSettings;
use App\Models\AppEcosystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('it creates default ecosystem settings with the provided windows setup url', function () {
    $settings = AppEcosystemSetting::current();

    expect($settings)->not->toBeNull()
        ->and($settings->desktop_windows_url)->toBe('https://github.com/baguidevos/links-vault/releases/download/v1.1.3/LinksVault-1.1.3-setup.exe')
        ->and($settings->desktop_version)->toBe('1.1.3')
        ->and($settings->desktop_is_active)->toBeTrue()
        ->and($settings->extension_chrome_is_active)->toBeTrue()
        ->and($settings->extension_firefox_is_active)->toBeFalse()
        ->and($settings->mobile_android_is_active)->toBeFalse()
        ->and($settings->mobile_ios_is_active)->toBeFalse();
});

test('desktop download route redirects to the configured windows executable url', function () {
    $settings = AppEcosystemSetting::current();

    $response = $this->get(route('desktop.download'));

    $response->assertRedirect($settings->getEffectiveDesktopWindowsUrl());
});

test('desktop download route reflects updated url dynamically', function () {
    $settings = AppEcosystemSetting::current();
    $newUrl = 'https://github.com/baguidevos/links-vault/releases/download/v1.0.5/LinksVault-1.0.5-setup.exe';

    $settings->update([
        'desktop_windows_url' => $newUrl,
        'desktop_version' => '1.0.5',
    ]);

    $response = $this->get(route('desktop.download'));

    $response->assertRedirect($newUrl);
});

test('firefox route redirects to AMO url when configured', function () {
    $settings = AppEcosystemSetting::current();
    $firefoxUrl = 'https://addons.mozilla.org/fr/firefox/addon/linksvault/';

    $settings->update([
        'extension_firefox_url' => $firefoxUrl,
        'extension_firefox_is_active' => true,
    ]);

    $response = $this->get(route('extension.firefox'));

    $response->assertRedirect($firefoxUrl);
});

test('landing page displays dynamic ecosystem versions and sections', function () {
    $settings = AppEcosystemSetting::current();
    $settings->update([
        'desktop_version' => '1.0.4',
        'mobile_notice' => 'Bientôt disponible sur Google Play & App Store',
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('LinksVault sur Smartphone', false)
        ->assertSee('v1.0.4', false)
        ->assertSee(route('desktop.download'), false);
});

test('non-admin user cannot access the ecosystem settings admin page', function () {
    $regularUser = User::factory()->create([
        'email' => 'regular@example.com',
        'is_admin' => false,
    ]);

    $this->actingAs($regularUser)
        ->get('/admin/ecosystem-settings')
        ->assertForbidden();
});

test('super admin can view ecosystem settings page in admin panel', function () {
    $admin = User::factory()->create([
        'email' => 'webmaster@gmail.com',
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/ecosystem-settings')
        ->assertOk();
});

test('super admin can update ecosystem settings through filament form', function () {
    $admin = User::factory()->create([
        'email' => 'webmaster@gmail.com',
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(ManageEcosystemSettings::class)
        ->fillForm([
            'desktop_version' => '1.0.6',
            'desktop_windows_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.0.6/LinksVault-1.0.6-setup.exe',
            'desktop_mac_url' => 'https://github.com/baguidevos/links-vault/releases/download/v1.0.6/LinksVault-1.0.6-arm64.dmg',
            'extension_firefox_is_active' => true,
            'extension_firefox_url' => 'https://addons.mozilla.org/firefox/addon/linksvault-official/',
            'mobile_android_is_active' => true,
            'mobile_android_url' => 'https://play.google.com/store/apps/details?id=com.linksvault.app',
            'mobile_notice' => 'Lancement officiel des applications mobiles !',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = AppEcosystemSetting::current()->fresh();

    expect($fresh->desktop_version)->toBe('1.0.6')
        ->and($fresh->desktop_windows_url)->toBe('https://github.com/baguidevos/links-vault/releases/download/v1.0.6/LinksVault-1.0.6-setup.exe')
        ->and($fresh->extension_firefox_is_active)->toBeTrue()
        ->and($fresh->mobile_android_is_active)->toBeTrue()
        ->and($fresh->mobile_android_url)->toBe('https://play.google.com/store/apps/details?id=com.linksvault.app')
        ->and($fresh->mobile_notice)->toBe('Lancement officiel des applications mobiles !');
});
