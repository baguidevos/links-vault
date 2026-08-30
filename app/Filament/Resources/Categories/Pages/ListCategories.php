<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\CategoryActions\CreateCategoryAction;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Catégories');
    }

    public function getBreadcrumb(): ?string
    {
        return __('Liste');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Classez vos ressources par grandes thématiques.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->form(CategoryForm::getComponents())
                ->label(__('Nouvelle catégorie'))
                ->icon(TablerIcon::Plus)
                ->action(function (array $data) {
                    CreateCategoryAction::execute($data);
                }),
        ];
    }

    public function notifications(): void
    {
        Notification::make()
            ->title(__('Catégorie créée avec succès'))
            ->success()
            ->send();
    }
}
