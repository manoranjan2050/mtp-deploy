<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Filament\Pages\Monitoring;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoringAiSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        Server::query()->create(['name' => 'Local Server', 'is_local' => true]);
        config(['services.anthropic.api_key' => 'sk-ant-test']);
    }

    public function test_an_admin_can_request_an_ai_health_summary(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'This server looks healthy, no active alerts.']],
            ]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(Monitoring::class)
            ->assertSee('Summarize with AI')
            ->call('aiHealthSummary')
            ->assertNotified();
    }

    public function test_a_viewer_does_not_see_the_ai_summary_button(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(Monitoring::class)
            ->assertDontSee('Summarize with AI');
    }

    public function test_a_viewer_cannot_call_the_ai_summary_method_directly(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(Monitoring::class)
            ->call('aiHealthSummary')
            ->assertForbidden();
    }
}
