<?php

declare(strict_types=1);

test('error pages render properly with brand styling', function (string $view) {
    $html = view("errors.{$view}")->render();

    expect($html)
        ->toContain('LinksVault')
        ->toContain('Accéder au Coffre-fort');
})->with([
    '401',
    '402',
    '403',
    '404',
    '419',
    '429',
    '500',
    '503',
    'link-expired',
]);
