<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use App\DTOs\System\SystemMetricsData;
use App\Enums\AlertMetric;
use App\Models\Alert;
use App\Models\Server;
use App\Services\Monitoring\AlertEvaluatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertEvaluatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_triggers_an_alert_when_a_metric_exceeds_its_threshold(): void
    {
        $server = $this->server(['cpu_alert_threshold' => 80]);

        app(AlertEvaluatorService::class)->evaluate($server, $this->metrics(cpuUsagePercent: 92.0));

        $this->assertDatabaseHas('alerts', [
            'server_id' => $server->id,
            'metric' => AlertMetric::Cpu->value,
            'threshold_percent' => 80,
            'triggered_value_percent' => 92.0,
        ]);
        $this->assertNull(Alert::query()->firstOrFail()->resolved_at);
    }

    public function test_it_does_not_duplicate_an_already_open_alert_for_the_same_metric(): void
    {
        $server = $this->server(['cpu_alert_threshold' => 80]);
        $evaluator = app(AlertEvaluatorService::class);

        $evaluator->evaluate($server, $this->metrics(cpuUsagePercent: 92.0));
        $evaluator->evaluate($server, $this->metrics(cpuUsagePercent: 95.0));

        $this->assertSame(1, Alert::query()->count());
    }

    public function test_it_resolves_an_open_alert_once_the_metric_drops_back_under_threshold(): void
    {
        $server = $this->server(['cpu_alert_threshold' => 80]);
        $evaluator = app(AlertEvaluatorService::class);

        $evaluator->evaluate($server, $this->metrics(cpuUsagePercent: 92.0));
        $evaluator->evaluate($server, $this->metrics(cpuUsagePercent: 50.0));

        $alert = Alert::query()->firstOrFail();
        $this->assertNotNull($alert->resolved_at);
    }

    public function test_it_ignores_a_metric_whose_threshold_is_not_configured(): void
    {
        $server = $this->server(['cpu_alert_threshold' => null]);

        app(AlertEvaluatorService::class)->evaluate($server, $this->metrics(cpuUsagePercent: 99.0));

        $this->assertSame(0, Alert::query()->count());
    }

    public function test_it_does_nothing_when_metrics_are_unsupported(): void
    {
        $server = $this->server(['cpu_alert_threshold' => 1]);

        app(AlertEvaluatorService::class)->evaluate($server, SystemMetricsData::unsupported());

        $this->assertSame(0, Alert::query()->count());
    }

    public function test_it_evaluates_memory_and_disk_independently_of_cpu(): void
    {
        $server = $this->server([
            'cpu_alert_threshold' => 80,
            'memory_alert_threshold' => 80,
            'disk_alert_threshold' => 80,
        ]);

        app(AlertEvaluatorService::class)->evaluate($server, $this->metrics(
            cpuUsagePercent: 10.0,
            memoryUsedBytes: 900,
            memoryTotalBytes: 1000,
            diskUsedBytes: 100,
            diskTotalBytes: 1000,
        ));

        $this->assertDatabaseHas('alerts', ['metric' => AlertMetric::Memory->value]);
        $this->assertDatabaseMissing('alerts', ['metric' => AlertMetric::Cpu->value]);
        $this->assertDatabaseMissing('alerts', ['metric' => AlertMetric::Disk->value]);
    }

    private function server(array $attributes): Server
    {
        return Server::query()->create(array_merge(['name' => 'Test Server', 'is_local' => true], $attributes));
    }

    private function metrics(
        float $cpuUsagePercent = 0.0,
        int $memoryUsedBytes = 1,
        int $memoryTotalBytes = 100,
        int $diskUsedBytes = 1,
        int $diskTotalBytes = 100,
    ): SystemMetricsData {
        return new SystemMetricsData(
            isSupported: true,
            cpuUsagePercent: $cpuUsagePercent,
            memoryUsedBytes: $memoryUsedBytes,
            memoryTotalBytes: $memoryTotalBytes,
            diskUsedBytes: $diskUsedBytes,
            diskTotalBytes: $diskTotalBytes,
            load1min: 0.1,
            load5min: 0.1,
            load15min: 0.1,
            networkRxBytes: 0,
            networkTxBytes: 0,
            collectedAt: Carbon::now(),
        );
    }
}
