<?php

declare(strict_types=1);

use Nafiswatsiq\Subbase\Models\Feature;
use Nafiswatsiq\Subbase\Models\Plan;
use Nafiswatsiq\Subbase\Models\Subscription;
use Nafiswatsiq\Subbase\Models\SubscriptionUsage;

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Tables
    |--------------------------------------------------------------------------
    |
    |
    */

    'tables' => [
        'plans' => 'plans',
        'features' => 'features',
        'subscriptions' => 'subscriptions',
        'subscription_usage' => 'subscription_usage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Models
    |--------------------------------------------------------------------------
    |
    | Models used to manage subscriptions. You can replace to use your own models,
    | but make sure that you have the same functionalities or that your models
    | extend from each model that you are going to replace.
    |
    */

    'models' => [
        'plan' => Plan::class,
        'feature' => Feature::class,
        'subscription' => Subscription::class,
        'subscription_usage' => SubscriptionUsage::class,
    ],

];
