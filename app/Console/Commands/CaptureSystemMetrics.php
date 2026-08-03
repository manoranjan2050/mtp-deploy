<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\SystemMetricSnapshot;
use App\Services\Monitoring\AlertEvaluatorService;
use App\Services\System\SystemMetricsService;
use Illuminate\Console\Command;

class CaptureSystemMetrics extends Command
{
    protected $signature = 'app:capture-system-metrics';

    protected $description = 'Capture a point-in-time system metrics snapshot for the Dashboard trend chart, evaluate alert thresholds, and prune old snapshots';

    public function handle(SystemMetricsService $metrics, AlertEvaluatorService $alerts): int
    {
        $data = $metrics->capture();

        SystemMetricSnapshot::query()->create([
            'is_supported' => $data->isSupported,
            'cpu_usage_percent' => $data->cpuUsagePercent,
            'memory_used_bytes' => $data->memoryUsedBytes,
            'memory_total_bytes' => $data->memoryTotalBytes,
            'disk_used_bytes' => $data->diskUsedBytes,
            'disk_total_bytes' => $data->diskTotalBytes,
            'load_1min' => $data->load1min,
            'load_5min' => $data->load5min,
            'load_15min' => $data->load15min,
            'network_rx_bytes' => $data->networkRxBytes,
            'network_tx_bytes' => $data->networkTxBytes,
            'recorded_at' => $data->collectedAt,
        ]);

        $localServer = Server::query()->where('is_local', true)->first();

        if ($localServer !== null) {
            $alerts->evaluate($localServer, $data);
        }

        SystemMetricSnapshot::query()
            ->where('recorded_at', '<', now()->subDays((int) config('mtp.metrics_retention_days')))
            ->delete();

        $this->info($data->isSupported ? 'Snapshot captured.' : 'Snapshot captured (unsupported OS - null metrics).');

        return self::SUCCESS;
    }
}
