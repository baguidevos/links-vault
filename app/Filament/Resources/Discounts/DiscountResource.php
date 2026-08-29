<?php

declare(strict_types=1);

namespace App\Filament\Resources\Discounts;

use Nafiswatsiq\Subbase\Filament\Resources\Discounts\DiscountResource as BaseDiscountResource;

class DiscountResource extends BaseDiscountResource
{
    protected static bool $isScopedToTenant = false;
}
