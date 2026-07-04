<?php

namespace App\Filament\Resources\Links\Pages;

use App\Actions\LinkActions\CreateLinkAction;
use App\Filament\Resources\Links\LinkResource;
use App\Filament\Resources\Links\Schemas\LinkForm;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLinks extends ListRecords
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->form(LinkForm::getComponents())
                ->label(__('Create Link'))
                ->icon(TablerIcon::Plus)
                ->action(function (array $data) {
                    CreateLinkAction::execute($data);
                }),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('create_link')
                ->label(__('Create Link'))
                ->icon(TablerIcon::Plus)
                ->url(LinkResource::getUrl('create'))
                ->color('primary'),
        ];
    }

    public function notifications(): void
    {
        Notification::make()
            ->title(__('Link created successfully'))
            ->success()
            ->send();
    }
}
