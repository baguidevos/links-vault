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
});
