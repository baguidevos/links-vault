<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Schemas;

use App\Enums\FolderRole;
use App\Enums\FolderVisibility;
use App\Models\Category;
use App\Models\User;
use FawazIwalewa\FilamentIconPicker\Forms\Components\IconPicker;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FolderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->components(self::getComponents()),
            ]);
    }

    public static function getComponents(): array
    {
        return [
            Grid::make(2)
                ->components([
                    TextInput::make('name')
                        ->label(__('Nom du dossier'))
                        ->required()
                        ->maxLength(255)
                        ->autofocus()
                        ->live(onBlur: false, debounce: '50ms')
                        ->afterStateUpdated(function ($state, Set $set) {
                            $set('slug', Str::slug($state));
                        }),
                    TextInput::make('slug')
                        ->label(__('Slug'))
                        ->required()
                        ->maxLength(255)
                        ->readOnly(),
                ]),

            Grid::make(2)
                ->components([
                    Select::make('category_id')
                        ->label(__('Catégorie parente (optionnel)'))
                        ->options(fn () => Category::pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->placeholder(__('Aucune catégorie (dossier racine)')),

                    Select::make('visibility')
                        ->label(__('Visibilité / Accès'))
                        ->options(collect(FolderVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                        ->default(FolderVisibility::Private->value)
                        ->required()
                        ->live()
                        ->helperText(fn (Get $get) => match ($get('visibility')) {
                            'private', FolderVisibility::Private->value => __('Visible uniquement par vous (et le propriétaire de l\'équipe).'),
                            'team', FolderVisibility::Team->value => __('Visible par tous les membres de cette équipe.'),
                            'restricted', FolderVisibility::Restricted->value => __('Visible uniquement par les membres spécifiés ci-dessous.'),
                            default => null,
                        }),
                ]),

            Section::make(__('Membres ayant accès à ce dossier'))
                ->description(__('Sélectionnez les membres de l\'équipe autorisés à consulter ou éditer ce dossier.'))
                ->visible(fn (Get $get) => in_array($get('visibility'), ['restricted', FolderVisibility::Restricted->value, FolderVisibility::Restricted]))
                ->schema([
                    Repeater::make('members')
                        ->relationship('members')
                        ->label('')
                        ->schema([
                            Select::make('user_id')
                                ->label(__('Membre de l\'équipe'))
                                ->options(function () {
                                    $team = Filament::getTenant();
                                    if (! $team) {
                                        return User::where('id', '!=', Auth::id())->pluck('name', 'id');
                                    }

                                    return $team->members()
                                        ->where('users.id', '!=', Auth::id())
                                        ->pluck('users.name', 'users.id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                            Select::make('role')
                                ->label(__('Droit / Rôle'))
                                ->options(collect(FolderRole::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                                ->default(FolderRole::Viewer->value)
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('Ajouter un membre')),
                ]),

            Grid::make(3)
                ->components([
                    ColorPicker::make('color')
                        ->label(__('Couleur'))
                        ->default('#0099FF'),

                    IconPicker::make('icon')
                        ->label(__('Icône'))
                        ->sets(['heroicons']),

                    TextInput::make('sort_order')
                        ->label(__('Ordre de tri'))
                        ->default(0)
                        ->numeric(),
                ]),

            MarkdownEditor::make('description')
                ->label(__('Description'))
                ->columnSpanFull()
                ->maxLength(500),
        ];
    }
}
