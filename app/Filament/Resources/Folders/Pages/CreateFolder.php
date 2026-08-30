<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Pages;

use App\Enums\FolderVisibility;
use App\Filament\Resources\Folders\FolderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;

    /**
     * Synchronise les membres du dossier via la table pivot folder_user après création.
     */
    protected function afterCreate(): void
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
        }
    }
}
