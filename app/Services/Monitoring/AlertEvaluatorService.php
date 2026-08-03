<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTOs\System\SystemMetricsData;
use App\Enums\AlertMetric;
use App\Models\Alert;
use App\Models\Server;
use App\Models\User;
use App\Services\Notifications\NotificationDispatchService;

/**
 * Pure decision logic over a metrics snapshot and a server's configured
 * thresholds (null = alerting disabled for that metric) - fully testable
 * without needing a real Linux host, since it only ever consumes an already-
 * captured `SystemMetricsData` DTO. At most one *unresolved* alert exists per
 * server/metric at a time: a breach that's already open is never duplicated,
 * and a metric back under its threshold auto-resolves the open alert.
 */
class AlertEvaluatorService
{
    public function __construct(
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function evaluate(Server $server, SystemMetricsData $metrics): void
    {
        if (! $metrics->isSupported) {
            return;
        }

        $this->evaluateMetric($server, AlertMetric::Cpu, $server->cpu_alert_threshold, $metrics->cpuUsagePercent, $metrics);
        $this->evaluateMetric($server, AlertMetric::Memory, $server->memory_alert_threshold, $metrics->memoryUsagePercent(), $metrics);
        $this->evaluateMetric($server, AlertMetric::Disk, $server->disk_alert_threshold, $metrics->diskUsagePercent(), $metrics);
    }

    private function evaluateMetric(Server $server, AlertMetric $metric, ?int $threshold, ?float $value, SystemMetricsData $metrics): void
    {
        if ($threshold === null || $value === null) {
            return;
        }

        $openAlert = $server->alerts()
            ->where('metric', $metric->value)
            ->whereNull('resolved_at')
            ->first();

        if ($value > $threshold) {
            if ($openAlert === null) {
                Alert::query()->create([
                    'server_id' => $server->id,
                    'metric' => $metric,
                    'threshold_percent' => $threshold,
                    'triggered_value_percent' => $value,
                    'triggered_at' => $metrics->collectedAt,
                ]);

                $this->notifications->notifyUsers(
                    User::role(['admin', 'super-admin'])->get(),
                    "MTP Deploy alert: {$metric->getLabel()} on {$server->name}",
                    "{$metric->getLabel()} reached {$value}%, above the configured threshold of {$threshold}%.",
                );
            }

            return;
        }

        $openAlert?->update(['resolved_at' => $metrics->collectedAt]);
    }
}
