<?php

namespace App\Filament\Resources\Links\Pages;

use App\Enums\LinkVisibility;
use App\Filament\Resources\Links\LinkResource;
use App\Services\SubscriptionQuotaService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateLink extends CreateRecord
{
    protected static string $resource = LinkResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('Ajouter un lien');
    }

    public function getBreadcrumb(): string
    {
        return __('Ajouter');
    }

    protected function beforeCreate(): void
    {
        $user = auth()->user();
        if ($user) {
            $quotaService = app(SubscriptionQuotaService::class);
            $tenant = Filament::getTenant();

            if (! $quotaService->canCreateLink($user, $tenant)) {
                Notification::make()
                    ->title(__('Limite de liens atteinte'))
                    ->body(__('Vous avez atteint la limite maximale autorisée par votre plan (:limit liens max). Veuillez passer au plan supérieur pour continuer à ajouter des liens.', [
                        'limit' => $quotaService->getLinksLimit($user),
                    ]))
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant && empty($data['team_id'])) {
            $data['team_id'] = $tenant->id;
        }

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
