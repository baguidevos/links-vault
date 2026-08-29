<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans;

use Nafiswatsiq\Subbase\Filament\Resources\Plans\PlanResource as BasePlanResource;

class PlanResource extends BasePlanResource
{
    protected static bool $isScopedToTenant = false;
}
