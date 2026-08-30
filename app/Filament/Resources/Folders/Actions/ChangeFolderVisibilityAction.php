<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Actions;

use App\Enums\FolderRole;
use App\Enums\FolderVisibility;
use App\Models\Folder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

class ChangeFolderVisibilityAction extends Action
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
            ->modalHeading(__('Modifier la visibilité du dossier'))
            ->modalDescription(__('Définissez qui peut consulter et éditer ce dossier dans l\'espace.'))
            ->modalWidth('lg')
            ->slideOver()
            ->visible(fn (?Folder $record) => $record ? $record->canChangeVisibility(Auth::user()) : false)
            ->fillForm(function (Folder $record): array {
                return [
                    'visibility' => $record->visibility instanceof FolderVisibility
                        ? $record->visibility->value
                        : (string) ($record->visibility ?? FolderVisibility::Private->value),
                    'members_data' => $record->members()->get()->map(fn ($member) => [
                        'user_id' => (string) $member->id,
                        'role' => $member->pivot->role ?? FolderRole::Viewer->value,
                    ])->toArray(),
                ];
            })
            ->form([
                Select::make('visibility')
                    ->label(__('Niveau de visibilité'))
                    ->options(collect(FolderVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                    ->required()
                    ->live()
                    ->helperText(fn (Get $get) => match ($get('visibility')) {
                        'private', FolderVisibility::Private->value => __('Visible uniquement par vous (et le propriétaire de l\'équipe).'),
                        'team', FolderVisibility::Team->value => __('Visible par tous les membres de cette équipe.'),
                        'restricted', FolderVisibility::Restricted->value => __('Visible uniquement par les membres spécifiés ci-dessous avec leurs rôles respectifs.'),
                        default => null,
                    }),

                Section::make(__('Membres ayant accès à ce dossier'))
                    ->description(__('Sélectionnez les membres de l\'équipe autorisés à consulter ou éditer ce dossier.'))
                    ->visible(fn (Get $get) => in_array($get('visibility'), ['restricted', FolderVisibility::Restricted->value, FolderVisibility::Restricted]))
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

                                Select::make('role')
                                    ->label(__('Droit / Rôle'))
                                    ->options(collect(FolderRole::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                                    ->default(FolderRole::Viewer->value)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('Ajouter un membre'))
                            ->default([]),
                    ]),
            ])
            ->action(function (array $data, Folder $record): void {
                $visibility = $data['visibility'];
                $record->update(['visibility' => $visibility]);

                if ($visibility === FolderVisibility::Restricted->value || $visibility === 'restricted') {
                    $syncData = [];
                    foreach ($data['members_data'] ?? [] as $item) {
                        if (! empty($item['user_id'])) {
                            $syncData[(int) $item['user_id']] = [
                                'role' => $item['role'] ?? FolderRole::Viewer->value,
                            ];
                        }
                    }

                    $record->members()->sync($syncData);
                } else {
                    $record->members()->detach();
                }

                Notification::make()
                    ->title(__('Visibilité du dossier mise à jour'))
                    ->success()
                    ->send();
            });
    }
}
