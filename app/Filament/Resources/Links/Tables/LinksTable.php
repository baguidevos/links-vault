<?php

namespace App\Filament\Resources\Links\Tables;

use App\Enums\ContentType;
use App\Enums\LinkVisibility;
use App\Filament\Resources\Links\Actions\ChangeVisibilityAction;
use App\Models\Folder;
use App\Models\Link;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class LinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                $query->with(['category', 'folder', 'tags']);

                if ($user) {
                    $query->accessibleForUser($user);
                }
            })
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label(__('Aperçu'))
                    ->getStateUsing(fn ($record) => $record->content_type === ContentType::Youtube ? $record->getYoutubeThumbnailUrl() : ($record->thumbnail_url ?: $record->favicon_url))
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab()
                    ->imageSize(44),
                IconColumn::make('is_favorite')
                    ->label(__('Favori'))
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->action(function (Link $record): void {
                        $record->update(['is_favorite' => ! $record->is_favorite]);
                        Notification::make()
                            ->title($record->is_favorite ? __('Ajouté aux favoris ⭐') : __('Retiré des favoris'))
                            ->success()
                            ->send();
                    })
                    ->alignCenter(),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('medium')
                    ->description(fn (Link $record): ?string => $record->description ? Str::limit($record->description, 45) : null),

                TextColumn::make('url')
                    ->label(__('URL'))
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true),
                TextColumn::make('folder.name')
                    ->label(__('Dossier'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('content_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (ContentType $state): string => $state->label())
                    ->color(fn (ContentType $state): string => match ($state) {
                        ContentType::Youtube => 'danger',
                        ContentType::GoogleDrive => 'warning',
                        ContentType::GoogleDoc => 'warning',
                        ContentType::GoogleSlides => 'warning',
                        ContentType::GoogleSheet => 'warning',
                        ContentType::GoogleForm => 'warning',
                        ContentType::Article => 'info',
                        ContentType::Pdf => 'gray',
                        ContentType::Image => 'success',
                        ContentType::Other => 'gray',
                    }),
                TextColumn::make('visibility')
                    ->label(__('Visibilité'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('visit_count')
                    ->label(__('Visits'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('folder')
                    ->relationship('folder', 'name')
                    ->label(__('Dossier'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label(__('Category'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('content_type')
                    ->label(__('Content Type'))
                    ->options(ContentType::class)
                    ->searchable(),
                SelectFilter::make('is_favorite')
                    ->label(__('Favorite'))
                    ->options([
                        '1' => __('Yes'),
                        '0' => __('No'),
                    ]),
                SelectFilter::make('is_archived')
                    ->label(__('Archived'))
                    ->options([
                        '1' => __('Yes'),
                        '0' => __('No'),
                    ])
                    ->query(fn ($query) => $query->where('is_archived', false)),
                SelectFilter::make('visibility')
                    ->label(__('Visibilité'))
                    ->options(collect(LinkVisibility::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                    ->searchable(),
            ], FiltersLayout::AboveContent)
            ->recordAction('view')
            ->recordActions([
                ViewAction::make('voir')
                    ->slideOver()
                    ->modalWidth('2xl'),
                Action::make('open_link')
                    ->label(__('Ouvrir'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Link $record): string => $record->url, shouldOpenInNewTab: true)
                    ->tooltip(__('Ouvrir dans le navigateur')),
                ChangeVisibilityAction::make(),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->after(function (Link $record, array $data) {
                        $vis = $record->visibility instanceof LinkVisibility
                            ? $record->visibility->value
                            : (string) $record->visibility;

                        if ($vis === LinkVisibility::Restricted->value) {
                            $membersData = $data['members_data'] ?? [];

                            $syncData = [];
                            foreach ($membersData as $item) {
                                if (! empty($item['user_id'])) {
                                    $syncData[] = (int) $item['user_id'];
                                }
                            }

                            $record->members()->sync($syncData);
                        } else {
                            $record->members()->detach();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign_to_folder')
                        ->label(__('Assigner à un dossier'))
                        ->icon(TablerIcon::FolderPlus)
                        ->color('primary')
                        ->form([
                            Select::make('folder_id')
                                ->label(__('Dossier de destination'))
                                ->options(function () {
                                    $user = auth()->user();
                                    $query = Folder::query();
                                    if ($user) {
                                        $query->accessibleForUser($user);
                                    }

                                    return $query->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder(__('Retirer du dossier actuel'))
                                ->helperText(__('Sélectionnez un dossier pour déplacer les liens ou laissez vide pour les isoler/retirer du dossier.')),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $folderId = ! empty($data['folder_id']) ? (int) $data['folder_id'] : null;
                            $folder = $folderId ? Folder::find($folderId) : null;

                            $count = 0;
                            foreach ($records as $record) {
                                $updateData = ['folder_id' => $folderId];
                                if ($folder && $folder->category_id && empty($record->category_id)) {
                                    $updateData['category_id'] = $folder->category_id;
                                }
                                $record->update($updateData);
                                $count++;
                            }

                            Notification::make()
                                ->title(__('Liens mis à jour'))
                                ->body(__(':count lien(s) assigné(s) au dossier.', ['count' => $count]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
