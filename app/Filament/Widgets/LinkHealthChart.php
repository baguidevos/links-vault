<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LinkHealthStatus;
use App\Models\Link;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LinkHealthChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'État de Santé des Liens';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $stats = Link::query()
            ->when($tenant, fn ($q) => $q->where('team_id', $tenant->id))
            ->select('health_status', DB::raw('count(*) as count'))
            ->groupBy('health_status')
            ->pluck('count', 'health_status')
            ->toArray();

        $healthy = (int) ($stats[LinkHealthStatus::Healthy->value] ?? $stats['healthy'] ?? 0);
        $redirect = (int) ($stats[LinkHealthStatus::Redirect->value] ?? $stats['redirect'] ?? 0);
        $broken = (int) ($stats[LinkHealthStatus::Broken->value] ?? $stats['broken'] ?? 0);
        $unknown = (int) ($stats[LinkHealthStatus::Unknown->value] ?? $stats['unknown'] ?? 0);

        $total = $healthy + $redirect + $broken + $unknown;

        if ($total === 0) {
            return [
                'datasets' => [
                    [
                        'label' => 'Liens',
                        'data' => [1],
                        'backgroundColor' => ['#94a3b8'],
                    ],
                ],
                'labels' => ['Aucun lien'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Santé des liens',
                    'data' => [$healthy, $redirect, $broken, $unknown],
                    'backgroundColor' => [
                        '#10B981', // Emerald 500
                        '#F59E0B', // Amber 500
                        '#EF4444', // Red 500
                        '#94A3B8', // Slate 400
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => [
                "Sains ({$healthy})",
                "Redirections ({$redirect})",
                "Morts ({$broken})",
                "Non vérifiés ({$unknown})",
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
