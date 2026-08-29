<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Link;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        $linkQuery = Link::query()->when($tenant, fn ($q) => $q->where('team_id', $tenant->id));
        $categoryQuery = Category::query()->when($tenant, fn ($q) => $q->where('team_id', $tenant->id));

        $totalLinks = (clone $linkQuery)->count();
        $totalCategories = (clone $categoryQuery)->count();
        $totalVisits = (clone $linkQuery)->sum('visit_count') ?? 0;
        $totalFavorites = (clone $linkQuery)->where('is_favorite', true)->count();

        return [
            Stat::make('Liens Enregistrés', $totalLinks)
                ->description('Dans cet espace de travail')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary')
                ->chart([7, 10, 14, 18, 24, 30, $totalLinks ?: 1]),

            Stat::make('Catégories', $totalCategories)
                ->description('Dossiers d\'organisation')
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),

            Stat::make('Total des Visites', number_format($totalVisits))
                ->description('Consultations globales')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success')
                ->chart([2, 5, 8, 15, 22, $totalVisits ?: 1]),

            Stat::make('Favoris', $totalFavorites)
                ->description('Liens mis en avant')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),
        ];
    }
}
