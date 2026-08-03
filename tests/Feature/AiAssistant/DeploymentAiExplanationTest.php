<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentTrigger;
use App\Enums\WebsiteFramework;
use App\Filament\Resources\Deployments\Pages\ListDeployments;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DeploymentAiExplanationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        config(['services.anthropic.api_key' => 'sk-ant-test']);
    }

    public function test_an_admin_can_get_an_ai_explanation_for_a_failed_deployment(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'The composer install step failed due to a missing extension.']],
            ]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $deployment = $this->failedDeployment();

        Livewire::actingAs($admin)
            ->test(ListDeployments::class)
            ->callTableAction('explainWithAi', $deployment)
            ->assertNotified();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ai_assistant',
            'subject_id' => $deployment->id,
            'description' => 'explained a failed deployment',
        ]);
    }

    public function test_a_viewer_without_ai_assistant_permission_cannot_use_it(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $deployment = $this->failedDeployment();

        Livewire::actingAs($viewer)
            ->test(ListDeployments::class)
            ->assertTableActionHidden('explainWithAi', $deployment);
    }

    private function failedDeployment(): Deployment
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $website = Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'ai-deploy-test.local',
            'document_root' => sys_get_temp_dir().'/mtp-ai-test',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);

        return Deployment::query()->create([
            'website_id' => $website->id,
            'branch' => 'main',
            'status' => DeploymentStatus::Failed,
            'triggered_by' => DeploymentTrigger::Manual,
            'log' => "\$ composer install\nComposer could not find a driver for ext-imagick",
        ]);
    }
}
