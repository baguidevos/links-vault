<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Actions\TagActions\CreateTagAction;
use App\Filament\Resources\Tags\Schemas\TagForm;
use App\Filament\Resources\Tags\TagResource;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Tags');
    }

    public function getBreadcrumb(): ?string
    {
        return __('Liste');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Étiquetez et filtrez rapidement vos liens.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->form(TagForm::getComponents())
                ->label(__('Nouveau tag'))
                ->icon(TablerIcon::Plus)
                ->action(function (array $data) {
                    CreateTagAction::execute($data);
                }),
        ];
    }

    public function notifications(): void
    {
        Notification::make()
            ->title(__('Tag créé avec succès'))
            ->success()
            ->send();
    }
}
