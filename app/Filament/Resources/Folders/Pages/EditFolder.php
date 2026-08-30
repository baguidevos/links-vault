<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Pages;

use App\Enums\FolderVisibility;
use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Synchronise les membres du dossier via la table pivot folder_user.
     */
    protected function afterSave(): void
    {
        $vis = $this->record->visibility instanceof FolderVisibility
            ? $this->record->visibility->value
            : (string) $this->record->visibility;

        if ($vis === FolderVisibility::Restricted->value) {
            $membersData = $this->data['members_data'] ?? [];

            $syncData = [];
            foreach ($membersData as $item) {
                if (! empty($item['user_id'])) {
                    $syncData[(int) $item['user_id']] = [
                        'role' => $item['role'] ?? 'viewer',
                    ];
                }
            }

            $this->record->members()->sync($syncData);
        } else {
            $this->record->members()->detach();
        }
    }
}
