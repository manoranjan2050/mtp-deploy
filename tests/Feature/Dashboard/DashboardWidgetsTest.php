<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Filament\Widgets\LatestDeploymentsWidget;
use App\Filament\Widgets\MetricsTrendChart;
use App\Filament\Widgets\ServiceStatusWidget;
use App\Filament\Widgets\SystemStatsOverview;
use App\Models\SystemMetricSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_system_stats_overview_renders_unsupported_state_on_non_linux(): void
    {
        Livewire::test(SystemStatsOverview::class)
            ->assertSee('Unavailable')
            ->assertSee('Linux');
    }

    public function test_service_status_widget_renders_php_version_and_service_statuses(): void
    {
        Livewire::test(ServiceStatusWidget::class)
            ->assertSee(PHP_VERSION)
            ->assertSee('Mariadb');
    }

    public function test_metrics_trend_chart_renders_with_no_snapshots(): void
    {
        Livewire::test(MetricsTrendChart::class)
            ->assertSuccessful();
    }

    public function test_metrics_trend_chart_renders_with_snapshots(): void
    {
        SystemMetricSnapshot::query()->create([
            'is_supported' => true,
            'cpu_usage_percent' => 42.5,
            'memory_used_bytes' => 512,
            'memory_total_bytes' => 1024,
            'disk_used_bytes' => 100,
            'disk_total_bytes' => 200,
            'load_1min' => 0.5,
            'load_5min' => 0.4,
            'load_15min' => 0.3,
            'network_rx_bytes' => 10,
            'network_tx_bytes' => 20,
            'recorded_at' => now(),
        ]);

        $component = Livewire::test(MetricsTrendChart::class);
        $component->assertSuccessful();

        // getData() is protected on Filament's ChartWidget - reflection is the
        // only way to assert on it directly rather than parsing rendered HTML.
        $method = new \ReflectionMethod($component->instance(), 'getData');
        $data = $method->invoke($component->instance());

        $this->assertSame([42.5], $data['datasets'][0]['data']);
        $this->assertSame([50.0], $data['datasets'][1]['data']);
    }

    public function test_latest_deployments_widget_renders_empty_state(): void
    {
        Livewire::test(LatestDeploymentsWidget::class)
            ->assertSee('No deployments yet');
    }

    public function test_dashboard_page_loads_for_an_authenticated_user(): void
    {
        $response = $this->get('/admin');

        $response->assertSuccessful();
    }
}
