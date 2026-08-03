<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageLogs;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ManageLogsAiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        config(['services.anthropic.api_key' => 'sk-ant-test']);

        $this->documentRoot = sys_get_temp_dir().'/mtp-ai-logs-test-'.uniqid();
        File::ensureDirectoryExists($this->documentRoot.'/storage/logs');
        File::put($this->documentRoot.'/storage/logs/laravel.log', "[2026-08-03] local.ERROR: something broke\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);

        parent::tearDown();
    }

    public function test_an_admin_can_analyze_the_current_log_with_ai(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'There is one error indicating something broke.']],
            ]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ManageLogs::class, ['record' => $this->website()->getKey()])
            ->set('activeSource', 'Laravel log')
            ->call('analyzeWithAi')
            ->assertNotified();
    }

    public function test_a_viewer_cannot_analyze_with_ai(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ManageLogs::class, ['record' => $this->website()->getKey()])
            ->call('analyzeWithAi')
            ->assertForbidden();
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'ai-logs-test.local',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);
    }
}
