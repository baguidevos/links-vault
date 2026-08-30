<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LinkVisibility: string implements HasColor, HasIcon, HasLabel
{
    case Private = 'private';
    case Team = 'team';
    case Restricted = 'restricted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Private => __('Privé (moi uniquement)'),
            self::Team => __('Toute l\'équipe'),
            self::Restricted => __('Membres restreints'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Private => 'gray',
            self::Team => 'success',
            self::Restricted => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Private => 'heroicon-m-lock-closed',
            self::Team => 'heroicon-m-user-group',
            self::Restricted => 'heroicon-m-user-plus',
        };
    }
}
