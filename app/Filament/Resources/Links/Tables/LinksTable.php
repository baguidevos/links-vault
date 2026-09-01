<?php

namespace App\Filament\Resources\Links\Tables;

use App\Actions\LinkActions\GenerateAiSummaryAction;
use App\Enums\ContentType;
use App\Enums\LinkVisibility;
use App\Filament\Resources\Links\Actions\ChangeVisibilityAction;
use App\Models\Folder;
use App\Models\Link;
use App\Services\ContentDetectionService;
use App\Services\WebPageMetadataService;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
        $livewire = $table->getLivewire();
        $isGridView = ($livewire && property_exists($livewire, 'viewMode'))
            ? $livewire->viewMode === 'grid'
            : session('links_view_mode', 'grid') === 'grid';

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                $query->with(['category', 'folder', 'tags']);

                if ($user) {
                    $query->accessibleForUser($user);
                }
            })
            ->searchable([
                'title',
                'url',
                'description',
                'objective',
                'folder.name',
                'category.name',
            ])
            ->searchPlaceholder(__('Rechercher un lien par titre, URL, description, dossier...'))
            ->searchDebounce('300ms')
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
            ], FiltersLayout::AfterContentCollapsible)
            ->when(
                $isGridView,
                fn (Table $t) => $t->content(fn () => view('filament.resources.links.components.links-grid-cards')),
                fn (Table $t) => $t
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
                    ->recordActions([
                        Action::make('open_link')
                            ->label(__('Ouvrir'))
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->color('gray')
                            ->iconButton()
                            ->tooltip(__('Ouvrir dans le navigateur'))
                            ->url(fn (Link $record): string => route('links.visit', $record), shouldOpenInNewTab: true),

                        ViewAction::make('voir')
                            ->label(__('Aperçu'))
                            ->icon('heroicon-o-eye')
                            ->iconButton()
                            ->tooltip(__('Aperçu détaillé'))
                            ->slideOver()
                            ->modalWidth('2xl'),

                        Action::make('generate_ai_summary')
                            ->label(__('Résumé IA'))
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->iconButton()
                            ->tooltip(__('Générer le résumé IA & tags'))
                            ->action(function (Link $record): void {
                                GenerateAiSummaryAction::execute($record);
                                Notification::make()
                                    ->title(__('Résumé IA généré avec succès !'))
                                    ->success()
                                    ->send();
                            }),

                        ActionGroup::make([
                            Action::make('refresh_metadata')
                                ->label(__('Actualiser miniature'))
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action(function (Link $record): void {
                                    $analysis = app(ContentDetectionService::class)->analyze($record->url);
                                    $meta = $analysis['metadata'] ?? [];
                                    $record->update([
                                        'content_type' => $analysis['type'] ?? $record->content_type,
                                        'metadata' => array_merge($record->getSafeMetadata(), $meta),
                                        'thumbnail_url' => $meta['image'] ?? $meta['og_image'] ?? $record->thumbnail_url,
                                        'favicon_url' => $meta['favicon'] ?? (new WebPageMetadataService)->fetchFavicon($record->url),
                                    ]);
                                    Notification::make()
                                        ->title(__('Miniature et métadonnées actualisées !'))
                                        ->success()
                                        ->send();
                                })
                                ->tooltip(__('Re-scanner la page web pour récupérer la miniature')),

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

                            DeleteAction::make(),
                        ])
                            ->icon('heroicon-m-ellipsis-vertical')
                            ->color('gray')
                            ->tooltip(__('Plus d\'actions')),
                    ])
                    ->toolbarActions([
                        BulkAction::make('refresh_metadata_bulk')
                            ->label(__('Actualiser les miniatures'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('gray')
                            ->action(function (Collection $records): void {
                                $detection = app(ContentDetectionService::class);
                                $metaService = new WebPageMetadataService;
                                foreach ($records as $record) {
                                    $analysis = $detection->analyze($record->url);
                                    $meta = $analysis['metadata'] ?? [];
                                    $record->update([
                                        'content_type' => $analysis['type'] ?? $record->content_type,
                                        'metadata' => array_merge($record->getSafeMetadata(), $meta),
                                        'thumbnail_url' => $meta['image'] ?? $meta['og_image'] ?? $record->thumbnail_url,
                                        'favicon_url' => $meta['favicon'] ?? $metaService->fetchFavicon($record->url),
                                    ]);
                                }
                                Notification::make()
                                    ->title(__(':count liens actualisés avec succès !', ['count' => $records->count()]))
                                    ->success()
                                    ->send();
                            })
                            ->deselectRecordsAfterCompletion(),

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
                        BulkActionGroup::make([

                            DeleteBulkAction::make(),
                        ]),
                    ])
            );
    }
}
