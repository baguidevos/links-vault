<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class LinksByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Répartition par Catégorie';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $categories = Category::query()
            ->when($tenant, fn ($q) => $q->where('team_id', $tenant->id))
            ->has('links')
            ->withCount(['links'])
            ->get();

        if ($categories->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Liens',
                        'data' => [1],
                        'backgroundColor' => ['#94a3b8'],
                    ],
                ],
                'labels' => ['Aucun lien classé'],
            ];
        }

        $colors = [
            '#0099FF',
            '#10B981',
            '#F59E0B',
            '#8B5CF6',
            '#EC4899',
            '#06B6D4',
            '#64748B',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Nombre de liens',
                    'data' => $categories->pluck('links_count')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $categories->count()),
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
