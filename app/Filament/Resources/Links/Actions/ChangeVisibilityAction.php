<?php

declare(strict_types=1);

namespace App\Filament\Resources\Links\Actions;

use App\Enums\LinkVisibility;
use App\Models\Link;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Get;
use Illuminate\Support\Facades\Auth;

class ChangeVisibilityAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'change_visibility';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Visibilité'))
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->modalHeading(__('Modifier la visibilité du lien'))
            ->modalDescription(__('Définissez qui peut consulter ce lien dans l\'espace.'))
            ->modalWidth('lg')
            ->slideOver()
            ->visible(fn (?Link $record) => $record ? $record->canChangeVisibility(Auth::user()) : false)
            ->fillForm(function (Link $record): array {
                return [
                    'visibility' => $record->visibility instanceof LinkVisibility
                        ? $record->visibility->value
                        : (string) ($record->visibility ?? LinkVisibility::Private->value),
                    'members_data' => $record->members->map(fn (User $u) => ['user_id' => $u->id])->toArray(),
                ];
            })
            ->form([
                Select::make('visibility')
                    ->label(__('Niveau de visibilité'))
                    ->options(collect(LinkVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get) => match ($get('visibility')) {
                        'private', LinkVisibility::Private->value => __('Visible uniquement par vous (et le propriétaire de l\'équipe).'),
                        'team', LinkVisibility::Team->value => __('Visible par tous les membres de cette équipe.'),
                        'restricted', LinkVisibility::Restricted->value => __('Visible uniquement par les membres spécifiés ci-dessous.'),
                        default => null,
                    }),

                Section::make(__('Membres autorisés'))
                    ->description(__('Sélectionnez les membres de l\'équipe autorisés à consulter ce lien.'))
                    ->visible(fn (Get $get) => in_array($get('visibility'), ['restricted', LinkVisibility::Restricted->value, LinkVisibility::Restricted]))
                    ->schema([
                        Repeater::make('members_data')
                            ->label('')
                            ->schema([
                                Select::make('user_id')
                                    ->label(__('Membre de l\'équipe'))
                                    ->options(function () {
                                        $team = Filament::getTenant();
                                        if (! $team) {
                                            return [];
                                        }

                                        return $team->members()
                                            ->where('users.id', '!=', Auth::id())
                                            ->pluck('users.name', 'users.id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct(),
                            ])
                            ->addActionLabel(__('Ajouter un membre'))
                            ->default([]),
                    ]),
            ])
            ->action(function (array $data, Link $record): void {
                $visibility = $data['visibility'];
                $record->update(['visibility' => $visibility]);

                if ($visibility === LinkVisibility::Restricted->value || $visibility === 'restricted') {
                    $syncData = collect($data['members_data'] ?? [])
                        ->pluck('user_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->toArray();

                    $record->members()->sync($syncData);
                } else {
                    $record->members()->detach();
                }

                Notification::make()
                    ->title(__('Visibilité mise à jour'))
                    ->success()
                    ->send();
            });
    }
}
