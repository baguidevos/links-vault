<?php

namespace App\Filament\Widgets;

use App\Models\Link;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LinksContentTypeChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Types de Contenus Sauvegardés';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $types = Link::query()
            ->when($tenant, fn ($q) => $q->where('team_id', $tenant->id))
            ->select('content_type', DB::raw('count(*) as count'))
            ->groupBy('content_type')
            ->get();

        if ($types->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Contenus',
                        'data' => [0],
                        'backgroundColor' => ['#0099FF'],
                    ],
                ],
                'labels' => ['Aucun contenu'],
            ];
        }

        $labels = $types->map(function ($item) {
            $type = $item->content_type;

            return is_object($type) && method_exists($type, 'label') ? $type->label() : ($type?->value ?? ($item->content_type ?: 'Autre'));
        })->toArray();

        $data = $types->pluck('count')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(0, 153, 255, 0.75)',
                        'rgba(16, 185, 129, 0.75)',
                        'rgba(245, 158, 11, 0.75)',
                        'rgba(139, 92, 246, 0.75)',
                        'rgba(236, 72, 153, 0.75)',
                    ],
                    'borderColor' => [
                        '#0099FF',
                        '#10B981',
                        '#F59E0B',
                        '#8B5CF6',
                        '#EC4899',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
