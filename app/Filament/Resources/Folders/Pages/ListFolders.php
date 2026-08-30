<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Pages;

use App\Actions\FolderActions\CreateFolderAction;
use App\Filament\Resources\Folders\FolderResource;
use App\Filament\Resources\Folders\Schemas\FolderForm;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFolders extends ListRecords
{
    protected static string $resource = FolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->form(FolderForm::getComponents())
                ->label(__('Nouveau dossier'))
                ->icon(TablerIcon::FolderPlus)
                ->action(function (array $data) {
                    CreateFolderAction::execute($data);
                }),
        ];
    }

    public function notifications(): void
    {
        Notification::make()
            ->title(__('Dossier créé avec succès'))
            ->success()
            ->send();
    }
}
