<?php

test('the landing page renders successfully with bilingual support, theme switcher, and FCFA pricing', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('LinksVault');
    $response->assertSee('800 FCFA');
    $response->assertSee('2 000 FCFA');
    $response->assertSee('theme-toggle', false);
    $response->assertSee('data-i18n', false);
    $response->assertSee('SasPay');
    $response->assertSee('Google Chrome');
    $response->assertSee('Mozilla Firefox');
    $response->assertSee('App Desktop');
    $response->assertSee('github.com/baguidevos/links-vault/releases', false);
    $response->assertSee(route('extension.download'), false);
});

test('desktop download route redirects to the configured desktop release url', function () {
    $response = $this->get(route('desktop.download'));

    $response->assertRedirect(\App\Models\AppEcosystemSetting::current()->getEffectiveDesktopWindowsUrl());
});
