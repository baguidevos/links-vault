<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Modifier le tag : :name', ['name' => $this->record->name]);
    }

    public function getBreadcrumb(): string
    {
        return __('Modifier');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
