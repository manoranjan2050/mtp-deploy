<?php

declare(strict_types=1);

namespace Tests\Feature\Websites;

use App\Filament\Resources\Websites\Pages\CreateWebsite;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the real Filament page (not just CreateWebsiteAction directly) -
 * this is what actually caught a real bug: Filament's Select already casts
 * its value to the WebsiteFramework enum instance, so calling ::from() on it
 * again in CreateWebsite::handleRecordCreation() threw a TypeError that a
 * test calling the Action directly could never have seen.
 */
class CreateWebsitePageTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->tempRoot = sys_get_temp_dir().'/mtp-deploy-test-'.uniqid();

        config([
            'mtp.nginx_sites_available_path' => $this->tempRoot.'/sites-available',
            'mtp.nginx_sites_enabled_path' => $this->tempRoot.'/sites-enabled',
            'mtp.sites_root' => $this->tempRoot.'/www',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_submitting_the_create_website_form_creates_and_provisions_a_website(): void
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        Livewire::test(CreateWebsite::class)
            ->fillForm([
                'server_id' => $server->id,
                'name' => 'Example',
                'domain' => 'example.test',
                'php_version' => '8.3',
                'framework' => 'laravel',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('websites', ['domain' => 'example.test']);
        $this->assertFileExists($this->tempRoot.'/sites-available/example.test.conf');
    }
}
