<?php

declare(strict_types=1);

namespace Tests\Feature\Backups;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageBackups;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ManageBackupsPageTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private string $backupsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->documentRoot = sys_get_temp_dir().'/mtp-managebackups-docroot-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);
        File::put("{$this->documentRoot}/index.php", 'hello');

        $this->backupsPath = sys_get_temp_dir().'/mtp-managebackups-storage-'.uniqid();
        config(['mtp.website_backups_path' => $this->backupsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);
        File::deleteDirectory($this->backupsPath);

        parent::tearDown();
    }

    public function test_an_admin_can_create_and_delete_a_files_backup(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageBackups::class, ['record' => $this->website()->getKey()])
            ->assertSuccessful()
            ->call('createBackup', 'files')
            ->assertSee('Success');

        $this->assertDatabaseHas('backups', ['type' => 'files', 'status' => 'success']);
    }

    public function test_saving_the_schedule_persists_settings(): void
    {
        $website = $this->website();

        Livewire::actingAs($this->admin())
            ->test(ManageBackups::class, ['record' => $website->getKey()])
            ->set('backupsEnabled', true)
            ->set('retentionCount', 14)
            ->call('saveSchedule');

        $this->assertTrue($website->fresh()->backups_enabled);
        $this->assertSame(14, $website->fresh()->backup_retention_count);
    }

    public function test_a_viewer_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ManageBackups::class, ['record' => $this->website()->getKey()])
            ->assertForbidden();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);
    }
}
