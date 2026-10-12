<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SasPay API Key
    |--------------------------------------------------------------------------
    |
    | Clé secrète d'API (sk_test_... en sandbox ou sk_live_... en production).
    | Ne doit JAMAIS être exposée au client ou dans le frontend.
    |
    */
    'api_key' => env('SASPAY_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | SasPay Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret de signature HMAC utilisé pour vérifier l'authenticité des
    | webhooks entrants envoyés par SasPay (affiché une seule fois à la création).
    |
    */
    'webhook_secret' => env('SASPAY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | SasPay Base URL
    |--------------------------------------------------------------------------
    |
    | L'URL de base officielle de l'API SasPay REST v1.
    |
    */
    'base_url' => env('SASPAY_BASE_URL', 'https://api.saspay.me/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Horodatage & Tolérance
    |--------------------------------------------------------------------------
    |
    | Tolérance maximale en secondes (300s = 5 minutes) autorisée entre
    | l'horloge du serveur et le header X-Webhook-Timestamp.
    |
    */
    'tolerance_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Mode de prise en charge des frais
    |--------------------------------------------------------------------------
    |
    | 'ADD_ON' : Les frais sont ajoutés au montant payé par le client.
    | 'DEDUCTED' : Les frais sont déduits du montant net reversé au marchand.
    |
    */
    'fee_charge_mode' => env('SASPAY_FEE_CHARGE_MODE', 'ADD_ON'),
];
