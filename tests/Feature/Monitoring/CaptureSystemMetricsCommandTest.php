<?php

declare(strict_types=1);

namespace Tests\Feature\Monitoring;

use App\Models\Alert;
use App\Models\Server;
use App\Models\SystemMetricSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureSystemMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_snapshot_on_every_run(): void
    {
        $this->artisan('app:capture-system-metrics')->assertSuccessful();

        $this->assertSame(1, SystemMetricSnapshot::query()->count());
    }

    public function test_it_prunes_snapshots_older_than_the_configured_retention(): void
    {
        config(['mtp.metrics_retention_days' => 7]);

        SystemMetricSnapshot::query()->create([
            'is_supported' => true,
            'cpu_usage_percent' => 1.0,
            'recorded_at' => now()->subDays(10),
        ]);

        $this->artisan('app:capture-system-metrics')->assertSuccessful();

        $this->assertSame(1, SystemMetricSnapshot::query()->count());
    }

    public function test_it_runs_alert_evaluation_against_the_local_server_without_error(): void
    {
        Server::query()->create(['name' => 'Local', 'is_local' => true, 'cpu_alert_threshold' => 1]);

        $this->artisan('app:capture-system-metrics')->assertSuccessful();

        if (PHP_OS_FAMILY !== 'Linux') {
            // Metrics are unsupported on this dev box, so evaluation is a
            // deterministic no-op - proves the command wires
            // AlertEvaluatorService in without crashing, without asserting on
            // real host CPU usage this test can't control.
            $this->assertSame(0, Alert::query()->count());
        }
    }
}
