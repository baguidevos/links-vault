<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Nouveau tag');
    }

    public function getBreadcrumb(): string
    {
        return __('Créer');
    }
}
