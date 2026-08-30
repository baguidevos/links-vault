<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FolderRole: string implements HasColor, HasLabel
{
    case Viewer = 'viewer';
    case Editor = 'editor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Viewer => __('Lecteur'),
            self::Editor => __('Éditeur'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Viewer => 'info',
            self::Editor => 'success',
        };
    }
}
