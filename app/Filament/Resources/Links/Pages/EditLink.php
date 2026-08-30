<?php

namespace App\Filament\Resources\Links\Pages;

use App\Enums\LinkVisibility;
use App\Filament\Resources\Links\LinkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditLink extends EditRecord
{
    protected static string $resource = LinkResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Modifier le lien : :title', ['title' => $this->record->title ?: $this->record->url]);
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
