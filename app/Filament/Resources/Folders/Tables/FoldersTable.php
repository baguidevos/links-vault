<?php

declare(strict_types=1);

namespace App\Filament\Resources\Folders\Tables;

use App\Enums\FolderVisibility;
use App\Filament\Resources\Folders\FolderResource;
use App\Models\Folder;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FoldersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Folder $record): string => FolderResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                $query->with(['category', 'members'])
                    ->withCount('links');

                if ($user) {
                    $query->accessibleForUser($user);
                }
            })
            ->columns([
                IconColumn::make('icon')
                    ->label(__('Icône'))
                    ->icon(fn ($state) => ! empty($state) ? $state : TablerIcon::Folder)
                    ->default(TablerIcon::Folder)
                    ->extraAttributes(fn ($record) => [
                        'style' => $record?->color ? "color: {$record->color} !important;" : '',
                    ]),

                TextColumn::make('name')
                    ->label(__('Nom'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label(__('Catégorie'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('visibility')
                    ->label(__('Visibilité'))
                    ->badge(),

                TextColumn::make('links_count')
                    ->label(__('Liens'))
                    ->counts('links')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                TextColumn::make('members.name')
                    ->label(__('Partagé avec'))
                    ->badge()
                    ->color('warning')
                    ->separator(', ')
                    ->limitList(3)
                    ->placeholder('—'),

                ColorColumn::make('color')
                    ->label(__('Couleur'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('Créé le'))
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label(__('Catégorie'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('visibility')
                    ->label(__('Visibilité'))
                    ->options(collect(FolderVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray()),
            ], FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
