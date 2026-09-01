<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Link;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LinksActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Activité d\'Ajout de Liens';

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30d';

    protected function getFilters(): ?array
    {
        return [
            '7d' => '7 derniers jours',
            '30d' => '30 derniers jours',
            '90d' => '3 derniers mois',
        ];
    }

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $days = match ($this->filter) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Récupération des ajouts groupés par jour
        $driver = DB::connection()->getDriverName();
        $dateExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
        };

        $records = Link::query()
            ->when($tenant, fn ($q) => $q->where('team_id', $tenant->id))
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->select(DB::raw("{$dateExpression} as date"), DB::raw('count(*) as count'))
            ->groupBy(DB::raw($dateExpression))
            ->pluck('count', 'date')
            ->toArray();

        $period = CarbonPeriod::create($startDate, '1 day', $endDate);

        $labels = [];
        $data = [];

        foreach ($period as $date) {
            $formattedKey = $date->format('Y-m-d');
            $labels[] = $date->translatedFormat($days <= 14 ? 'D j M' : 'j M');
            $data[] = (int) ($records[$formattedKey] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Liens ajoutés',
                    'data' => $data,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(0, 153, 255, 0.15)',
                    'borderColor' => '#0099FF',
                    'tension' => 0.35,
                    'pointBackgroundColor' => '#0099FF',
                    'pointRadius' => $days <= 14 ? 4 : 2,
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
