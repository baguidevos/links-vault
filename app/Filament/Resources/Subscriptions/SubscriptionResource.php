<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions;

use Nafiswatsiq\Subbase\Filament\Resources\Subscriptions\SubscriptionResource as BaseSubscriptionResource;

class SubscriptionResource extends BaseSubscriptionResource
{
    protected static bool $isScopedToTenant = false;
}
