<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Links\LinkResource;
use App\Models\Link;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestLinksWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Derniers Liens Ajoutés';

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        return $table
            ->query(
                Link::query()
                    ->when($tenant, fn ($q) => $q->where('team_id', $tenant->id))
                    ->with(['category'])
                    ->latest()
            )
            ->columns([
                ImageColumn::make('favicon_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://www.google.com/s2/favicons?domain='.(parse_url($record->url, PHP_URL_HOST) ?? 'google.com'))
                    ->size(28),

                TextColumn::make('title')
                    ->label('Titre')
                    ->description(fn (Link $record): string => $record->url)
                    ->searchable()
                    ->weight('semibold')
                    ->color('primary')
                    ->url(fn (Link $record) => LinkResource::getUrl('view', ['record' => $record->id])),

                TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('content_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_object($state) && method_exists($state, 'label') ? $state->label() : ($state?->value ?? $state))
                    ->color('gray'),

                TextColumn::make('visit_count')
                    ->label('Vues')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('created_at')
                    ->label('Ajouté')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Link $record) => LinkResource::getUrl('view', ['record' => $record->id])),

                Action::make('open')
                    ->label('Ouvrir')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Link $record) => $record->url, shouldOpenInNewTab: true),
            ])
            ->paginated([5]);
    }
}
