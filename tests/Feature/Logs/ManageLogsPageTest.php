<?php

declare(strict_types=1);

namespace Tests\Feature\Logs;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageLogs;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ManageLogsPageTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->documentRoot = sys_get_temp_dir().'/mtp-logs-page-test-'.uniqid();
        File::ensureDirectoryExists($this->documentRoot.'/storage/logs');
        File::put($this->documentRoot.'/storage/logs/laravel.log', "[2026-08-03] local.ERROR: something broke\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);

        parent::tearDown();
    }

    public function test_a_viewer_can_see_the_laravel_log_content(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ManageLogs::class, ['record' => $this->website()->getKey()])
            ->assertSuccessful()
            ->set('activeSource', 'Laravel log')
            ->assertSee('something broke');
    }

    public function test_search_filters_lines(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        File::append($this->documentRoot.'/storage/logs/laravel.log', "[2026-08-03] local.INFO: all good\n");

        Livewire::actingAs($admin)
            ->test(ManageLogs::class, ['record' => $this->website()->getKey()])
            ->set('activeSource', 'Laravel log')
            ->set('query', 'broke')
            ->assertSee('something broke')
            ->assertDontSee('all good');
    }

    public function test_a_static_website_has_no_laravel_log_source(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $website = $this->website(WebsiteFramework::Static);

        Livewire::actingAs($admin)
            ->test(ManageLogs::class, ['record' => $website->getKey()])
            ->assertSuccessful()
            ->assertDontSee('Laravel log');
    }

    public function test_a_user_without_view_access_cannot_open_the_page(): void
    {
        $stranger = User::factory()->create();

        Livewire::actingAs($stranger)
            ->test(ManageLogs::class, ['record' => $this->website()->getKey()])
            ->assertForbidden();
    }

    private function website(WebsiteFramework $framework = WebsiteFramework::Laravel): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => $framework,
        ]);
    }
}
