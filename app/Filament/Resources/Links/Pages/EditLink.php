<?php

namespace App\Filament\Resources\Links\Pages;

use App\Enums\LinkVisibility;
use App\Filament\Resources\Links\LinkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLink extends EditRecord
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Synchronise les membres autorisés pour ce lien via la table pivot link_user.
     */
    protected function afterSave(): void
    {
        $vis = $this->record->visibility instanceof LinkVisibility
            ? $this->record->visibility->value
            : (string) $this->record->visibility;

        if ($vis === LinkVisibility::Restricted->value) {
            $membersData = $this->data['members_data'] ?? [];

            $syncData = [];
            foreach ($membersData as $item) {
                if (! empty($item['user_id'])) {
                    $syncData[] = (int) $item['user_id'];
                }
            }

            $this->record->members()->sync($syncData);
        } else {
            $this->record->members()->detach();
        }
    }
}
