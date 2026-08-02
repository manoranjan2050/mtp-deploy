<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\System\SystemMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class SystemStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $metrics = app(SystemMetricsService::class)->capture();

        if (! $metrics->isSupported) {
            return [
                Stat::make('CPU usage', 'Unavailable')
                    ->description('Live metrics require Linux (this host is '.PHP_OS_FAMILY.')')
                    ->color('gray'),
                Stat::make('Memory usage', 'Unavailable')->color('gray'),
                Stat::make('Disk usage', 'Unavailable')->color('gray'),
                Stat::make('Load average', 'Unavailable')->color('gray'),
            ];
        }

        return [
            Stat::make('CPU usage', $metrics->cpuUsagePercent.'%')
                ->color($metrics->cpuUsagePercent > 85 ? 'danger' : 'success'),
            Stat::make('Memory usage', $metrics->memoryUsagePercent().'%')
                ->description(Number::fileSize($metrics->memoryUsedBytes).' / '.Number::fileSize($metrics->memoryTotalBytes))
                ->color($metrics->memoryUsagePercent() > 85 ? 'danger' : 'success'),
            Stat::make('Disk usage', $metrics->diskUsagePercent().'%')
                ->description(Number::fileSize($metrics->diskUsedBytes).' / '.Number::fileSize($metrics->diskTotalBytes))
                ->color($metrics->diskUsagePercent() > 85 ? 'danger' : 'success'),
            Stat::make('Load average', "{$metrics->load1min} / {$metrics->load5min} / {$metrics->load15min}")
                ->description('1 / 5 / 15 minute'),
        ];
    }
}
