<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Pages;

use App\Enums\FolderVisibility;
use App\Filament\Resources\Folders\FolderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Modifier le dossier : :name', ['name' => $this->record->name]);
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
