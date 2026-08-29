<?php

declare(strict_types=1);

use App\Models\User;
use Nafiswatsiq\Subbase\Models\Discount;
use Nafiswatsiq\Subbase\Models\Feature;
use Nafiswatsiq\Subbase\Models\Plan;
use Nafiswatsiq\Subbase\Models\Subscription;
use Nafiswatsiq\Subbase\Models\SubscriptionUsage;

return [

    /*
    |--------------------------------------------------------------------------
    | Locale Currency Mapping
    |--------------------------------------------------------------------------
    |
    | Configure locale to currency mapping for multi-language plan prices.
    | Key can be locale code (en, id, ms, en-US) and value must be
    | ISO 4217 currency code.
    |
    */

    'default_currency' => 'USD',

    'locale_currency_map' => [
        'af' => 'ZAR', // Afrikaans (South Africa)
        'ar' => 'SAR', // Arabic (Saudi Arabia)
        'az' => 'AZN', // Azerbaijani (Azerbaijan)
        'be' => 'BYN', // Belarusian (Belarus)
        'bg' => 'BGN', // Bulgarian (Bulgaria)
        'bn' => 'BDT', // Bengali (Bangladesh)
        'bs' => 'BAM', // Bosnian (Bosnia and Herzegovina)
        'ca' => 'EUR', // Catalan (Spain/Andorra)
        'cs' => 'CZK', // Czech (Czech Republic)
        'da' => 'DKK', // Danish (Denmark)
        'de' => 'EUR', // German (Germany)
        'el' => 'EUR', // Greek (Greece)
        'en' => 'USD', // English (United States)
        'en-US' => 'USD', // English (United States)
        'en-GB' => 'GBP', // English (United Kingdom)
        'en-AU' => 'AUD', // English (Australia)
        'en-CA' => 'CAD', // English (Canada)
        'en-IN' => 'INR', // English (India)
        'en-NZ' => 'NZD', // English (New Zealand)
        'es' => 'EUR', // Spanish (Spain)
        'es-ES' => 'EUR', // Spanish (Spain)
        'es-MX' => 'MXN', // Spanish (Mexico)
        'et' => 'EUR', // Estonian (Estonia)
        'fa' => 'IRR', // Persian (Iran)
        'fi' => 'EUR', // Finnish (Finland)
        'fr' => 'EUR', // French (France)
        'fr-FR' => 'EUR', // French (France)
        'fr-CA' => 'CAD', // French (Canada)
        'fr-CI' => 'XOF', // Côte d'Ivoire (Franc CFA)
        'fr-SN' => 'XOF', // Sénégal (Franc CFA)
        'fr-BJ' => 'XOF', // Bénin (Franc CFA)
        'fr-TG' => 'XOF', // Togo (Franc CFA)
        'fr-CM' => 'XAF', // Cameroun (Franc CFA)
        'en-NG' => 'NGN', // Nigeria (Naira)
        'en-GH' => 'GHS', // Ghana (Cedi)
        'en-KE' => 'KES', // Kenya (Shilling)
        'he' => 'ILS', // Hebrew (Israel)
        'hi' => 'INR', // Hindi (India)
        'hr' => 'EUR', // Croatian (Croatia)
        'hu' => 'HUF', // Hungarian (Hungary)
        'hy' => 'AMD', // Armenian (Armenia)
        'id' => 'IDR', // Indonesian (Indonesia)
        'is' => 'ISK', // Icelandic (Iceland)
        'it' => 'EUR', // Italian (Italy)
        'ja' => 'JPY', // Japanese (Japan)
        'ka' => 'GEL', // Georgian (Georgia)
        'kk' => 'KZT', // Kazakh (Kazakhstan)
        'ko' => 'KRW', // Korean (South Korea)
        'lt' => 'EUR', // Lithuanian (Lithuania)
        'lv' => 'EUR', // Latvian (Latvia)
        'mk' => 'MKD', // Macedonian (North Macedonia)
        'ms' => 'MYR', // Malay (Malaysia)
        'nb' => 'NOK', // Norwegian Bokmål (Norway)
        'nl' => 'EUR', // Dutch (Netherlands)
        'nn' => 'NOK', // Norwegian Nynorsk (Norway)
        'pl' => 'PLN', // Polish (Poland)
        'pt' => 'BRL', // Portuguese (Brazil)
        'pt-BR' => 'BRL', // Portuguese (Brazil)
        'pt-PT' => 'EUR', // Portuguese (Portugal)
        'ro' => 'RON', // Romanian (Romania)
        'ru' => 'RUB', // Russian (Russia)
        'sk' => 'EUR', // Slovak (Slovakia)
        'sl' => 'EUR', // Slovenian (Slovenia)
        'sq' => 'ALL', // Albanian (Albania)
        'sr' => 'RSD', // Serbian (Serbia)
        'sv' => 'SEK', // Swedish (Sweden)
        'th' => 'THB', // Thai (Thailand)
        'tr' => 'TRY', // Turkish (Turkey)
        'uk' => 'UAH', // Ukrainian (Ukraine) - Fixed from GBP
        'ur' => 'PKR', // Urdu (Pakistan)
        'uz' => 'UZS', // Uzbek (Uzbekistan)
        'vi' => 'VND', // Vietnamese (Vietnam)
        'zh' => 'CNY', // Chinese (China)
        'zh-CN' => 'CNY', // Chinese (China)
        'zh-TW' => 'TWD', // Chinese (Taiwan)
    ],

    'language_locale_map' => [
        'af' => 'Afrikaans',
        'ar' => 'Arabic',
        'az' => 'Azerbaijani',
        'be' => 'Belarusian',
        'bg' => 'Bulgarian',
        'bn' => 'Bengali',
        'bs' => 'Bosnian',
        'ca' => 'Catalan',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'el' => 'Greek',
        'en' => 'English',
        'en-US' => 'English (United States)',
        'en-GB' => 'English (United Kingdom)',
        'en-AU' => 'English (Australia)',
        'en-CA' => 'English (Canada)',
        'en-IN' => 'English (India)',
        'en-NZ' => 'English (New Zealand)',
        'es' => 'Spanish',
        'es-ES' => 'Spanish (Spain)',
        'es-MX' => 'Spanish (Mexico)',
        'et' => 'Estonian',
        'fa' => 'Persian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'fr-FR' => 'French (France)',
        'fr-CA' => 'French (Canada)',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hr' => 'Croatian',
        'hu' => 'Hungarian',
        'hy' => 'Armenian',
        'id' => 'Indonesian',
        'is' => 'Icelandic',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ka' => 'Georgian',
        'kk' => 'Kazakh',
        'ko' => 'Korean',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'mk' => 'Macedonian',
        'ms' => 'Malay',
        'nb' => 'Norwegian Bokmål',
        'nl' => 'Dutch',
        'nn' => 'Norwegian Nynorsk',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'pt-BR' => 'Portuguese (Brazil)',
        'pt-PT' => 'Portuguese (Portugal)',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sq' => 'Albanian',
        'sr' => 'Serbian',
        'sv' => 'Swedish',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'vi' => 'Vietnamese',
        'zh' => 'Chinese',
        'zh-CN' => 'Chinese (China)',
        'zh-TW' => 'Chinese (Taiwan)',
    ],

    'tables' => [
        'plans' => 'plans',
        'features' => 'features',
        'subscriptions' => 'subscriptions',
        'subscription_usage' => 'subscription_usage',
        'discounts' => 'discounts',
    ],

    'permissions' => [
        'plan' => null,
        'subscription' => null,
        'discount' => null,
    ],

    'models' => [
        'user' => config('auth.providers.users.model', User::class),
        'plan' => Plan::class,
        'feature' => Feature::class,
        'subscription' => Subscription::class,
        'subscription_usage' => SubscriptionUsage::class,
        'discount' => Discount::class,
    ],
];
