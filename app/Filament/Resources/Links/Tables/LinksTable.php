<?php

namespace App\Filament\Resources\Links\Tables;

use App\Enums\ContentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'tags']))
            ->columns([
                // ImageColumn::make('thumbnail_url')
                //     ->label(__('thumbnail_url'))
                //     ->circular()
                //     ->defaultImageUrl(fn($record) => $record->favicon_url)
                //     ->imageSize(40),

                ImageColumn::make('thumbnail_url')
                    ->label(__('thumbnail_url'))
                    ->getStateUsing(fn ($record) => $record->content_type === ContentType::Youtube ? $record->getYoutubeThumbnailUrl() : $record->favicon_url)
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab()
                    ->square(),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('medium'),
                TextColumn::make('url')
                    ->label(__('URL'))
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true),
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
            ], FiltersLayout::AboveContent)
            ->recordAction('view')
            ->recordActions([
                ViewAction::make('voir')
                    ->slideOver()
                    // ->view('filament.resources.links.pages.view')
                    ->modalWidth('2xl'),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('2xl'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
