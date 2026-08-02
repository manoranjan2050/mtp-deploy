<?php

declare(strict_types=1);

namespace Tests\Feature\Websites;

use App\Actions\Websites\ChangePhpVersionAction;
use App\Actions\Websites\CloneWebsiteAction;
use App\Actions\Websites\CreateWebsiteAction;
use App\Actions\Websites\DeleteWebsiteAction;
use App\Actions\Websites\SuspendWebsiteAction;
use App\Actions\Websites\ToggleSslAction;
use App\DTOs\Websites\CreateWebsiteData;
use App\Enums\SslStatus;
use App\Enums\WebsiteFramework;
use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebsiteActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().'/mtp-deploy-test-'.uniqid();

        config([
            'mtp.nginx_sites_available_path' => $this->tempRoot.'/sites-available',
            'mtp.nginx_sites_enabled_path' => $this->tempRoot.'/sites-enabled',
            'mtp.sites_root' => $this->tempRoot.'/www',
        ]);

        $this->server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_create_website_action_creates_a_record_and_provisions_it(): void
    {
        $result = app(CreateWebsiteAction::class)->handle(new CreateWebsiteData(
            serverId: $this->server->id,
            name: 'Example',
            domain: 'example.com',
            phpVersion: '8.3',
            framework: WebsiteFramework::Laravel,
        ));

        $this->assertDatabaseHas('websites', ['domain' => 'example.com']);
        $this->assertFileExists($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertSame('example.com', $result['website']->domain);
    }

    public function test_delete_website_action_deprovisions_and_soft_deletes(): void
    {
        $website = $this->createWebsite();

        app(DeleteWebsiteAction::class)->handle($website);

        $this->assertFileDoesNotExist($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertSoftDeleted('websites', ['id' => $website->id]);
    }

    public function test_suspend_website_action_toggles_status(): void
    {
        $website = $this->createWebsite();

        app(SuspendWebsiteAction::class)->handle($website);
        $this->assertSame(WebsiteStatus::Suspended, $website->fresh()->status);

        app(SuspendWebsiteAction::class)->handle($website->fresh());
        $this->assertSame(WebsiteStatus::Active, $website->fresh()->status);
    }

    public function test_clone_website_action_copies_files_and_creates_a_new_record(): void
    {
        $website = $this->createWebsite();
        File::put($website->publicPath().'/index.php', '<?php echo "hi";');

        $result = app(CloneWebsiteAction::class)->handle($website, 'clone.example.com');

        $this->assertDatabaseHas('websites', ['domain' => 'clone.example.com']);
        $this->assertFileExists($result['website']->publicPath().'/index.php');
    }

    public function test_change_php_version_action_updates_the_website_and_republishes(): void
    {
        $website = $this->createWebsite();

        app(ChangePhpVersionAction::class)->handle($website, '8.4');

        $this->assertSame('8.4', $website->fresh()->php_version);

        $config = File::get($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertStringContainsString('php8.4-fpm.sock', $config);
    }

    public function test_toggle_ssl_action_marks_pending_then_none(): void
    {
        $website = $this->createWebsite();
        $this->assertSame(SslStatus::None, $website->ssl_status);

        $toggled = app(ToggleSslAction::class)->enable($website);
        $this->assertSame(SslStatus::Pending, $toggled->ssl_status);

        $toggled = app(ToggleSslAction::class)->disable($toggled);
        $this->assertSame(SslStatus::None, $toggled->ssl_status);
    }

    private function createWebsite(): Website
    {
        return app(CreateWebsiteAction::class)->handle(new CreateWebsiteData(
            serverId: $this->server->id,
            name: 'Example',
            domain: 'example.com',
            phpVersion: '8.3',
            framework: WebsiteFramework::Laravel,
        ))['website'];
    }
}
