<?php

declare(strict_types=1);

namespace Tests\Feature\Monitoring;

use App\Enums\AlertMetric;
use App\Filament\Pages\Monitoring;
use App\Models\Alert;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoringPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_any_authenticated_user_can_view_the_page(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->server();

        Livewire::actingAs($viewer)
            ->test(Monitoring::class)
            ->assertSuccessful();
    }

    public function test_an_admin_can_save_alert_thresholds(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $server = $this->server();

        Livewire::actingAs($admin)
            ->test(Monitoring::class)
            ->set('cpuThreshold', 85)
            ->set('memoryThreshold', 90)
            ->set('diskThreshold', 95)
            ->call('saveThresholds');

        $this->assertSame(85, $server->fresh()->cpu_alert_threshold);
        $this->assertSame(90, $server->fresh()->memory_alert_threshold);
        $this->assertSame(95, $server->fresh()->disk_alert_threshold);
    }

    public function test_a_viewer_cannot_save_alert_thresholds(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->server();

        Livewire::actingAs($viewer)
            ->test(Monitoring::class)
            ->set('cpuThreshold', 85)
            ->call('saveThresholds')
            ->assertForbidden();
    }

    public function test_an_admin_can_resolve_an_active_alert(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $server = $this->server();
        $alert = Alert::query()->create([
            'server_id' => $server->id,
            'metric' => AlertMetric::Cpu,
            'threshold_percent' => 80,
            'triggered_value_percent' => 95.0,
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(Monitoring::class)
            ->call('resolveAlert', $alert->id);

        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_a_viewer_cannot_resolve_an_alert(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $server = $this->server();
        $alert = Alert::query()->create([
            'server_id' => $server->id,
            'metric' => AlertMetric::Cpu,
            'threshold_percent' => 80,
            'triggered_value_percent' => 95.0,
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($viewer)
            ->test(Monitoring::class)
            ->call('resolveAlert', $alert->id)
            ->assertForbidden();

        $this->assertNull($alert->fresh()->resolved_at);
    }

    private function server(): Server
    {
        return Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }
}
