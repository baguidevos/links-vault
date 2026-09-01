<?php

declare(strict_types=1);

namespace App\Enums;

use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LinkHealthStatus: string implements HasColor, HasIcon, HasLabel
{
    case Healthy = 'healthy';
    case Redirect = 'redirect';
    case Broken = 'broken';
    case Unknown = 'unknown';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Healthy => 'En ligne (Accessible)',
            self::Redirect => 'Redirection',
            self::Broken => 'Lien mort / Inaccessible',
            self::Unknown => 'Non vérifié',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Redirect => 'warning',
            self::Broken => 'danger',
            self::Unknown => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Healthy => TablerIcon::Check,
            self::Redirect => TablerIcon::ArrowRight,
            self::Broken => TablerIcon::AlertTriangle,
            self::Unknown => TablerIcon::Help,
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::Healthy;
    }

    public function isBroken(): bool
    {
        return $this === self::Broken;
    }

    public function isRedirect(): bool
    {
        return $this === self::Redirect;
    }
}
