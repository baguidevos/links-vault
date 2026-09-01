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

        // Calcul du taux de santé des liens
        $healthyCount = (clone $linkQuery)->where('health_status', 'healthy')->count();
        $brokenCount = (clone $linkQuery)->where('health_status', 'broken')->count();
        $healthPercent = $totalLinks > 0 ? (int) round(($healthyCount / $totalLinks) * 100) : 100;

        // Sparkline des 7 derniers jours d'ajouts
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = now()->subDays($i)->startOfDay();
            $dayEnd = now()->subDays($i)->endOfDay();
            $sparkline[] = (clone $linkQuery)
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<=', $dayEnd)
                ->count();
        }

        return [
            Stat::make('Liens Enregistrés', (string) $totalLinks)
                ->description('Dans cet espace de travail')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary')
                ->chart($sparkline),

            Stat::make('Catégories & Dossiers', (string) $totalCategories)
                ->description('Organisation thématique')
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),

            Stat::make('Total des Visites', number_format((int) $totalVisits))
                ->description('Consultations globales')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success'),

            Stat::make('Favoris', (string) $totalFavorites)
                ->description('Liens mis en avant')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Santé du Vault', "{$healthPercent}%")
                ->description($brokenCount > 0 ? "{$brokenCount} lien(s) mort(s) détecté(s)" : 'Tous les liens sont opérationnels')
                ->descriptionIcon($brokenCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->color($brokenCount > 0 ? 'danger' : 'success'),
        ];
    }
}
