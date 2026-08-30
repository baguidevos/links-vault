<?php

namespace App\Filament\Resources\Links\Pages;

use App\Enums\LinkVisibility;
use App\Filament\Resources\Links\LinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLink extends CreateRecord
{
    protected static string $resource = LinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = implode(',', $data['tags']);
        }

        return $data;
    }

    /**
     * Synchronise les membres autorisés pour ce lien après création.
     */
    protected function afterCreate(): void
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
        }
    }
}
