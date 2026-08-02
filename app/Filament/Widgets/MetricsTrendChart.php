<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SystemMetricSnapshot;
use Filament\Widgets\ChartWidget;

class MetricsTrendChart extends ChartWidget
{
    protected ?string $heading = 'CPU & Memory - last 60 snapshots';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $snapshots = SystemMetricSnapshot::query()
            ->where('is_supported', true)
            ->latest('recorded_at')
            ->limit(60)
            ->get()
            ->reverse();

        if ($snapshots->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'CPU %',
                    'data' => $snapshots->pluck('cpu_usage_percent')->all(),
                    'borderColor' => '#f59e0b',
                    'fill' => false,
                ],
                [
                    'label' => 'Memory %',
                    'data' => $snapshots->map(
                        fn (SystemMetricSnapshot $s): ?float => $s->memory_total_bytes
                            ? round(($s->memory_used_bytes / $s->memory_total_bytes) * 100, 1)
                            : null
                    )->all(),
                    'borderColor' => '#3b82f6',
                    'fill' => false,
                ],
            ],
            'labels' => $snapshots->pluck('recorded_at')->map(fn ($t) => $t->format('H:i'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
