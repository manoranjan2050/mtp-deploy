<?php

declare(strict_types=1);

namespace Tests\Feature\Websites;

use App\Enums\WebsiteFramework;
use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebsiteProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Point every mtp.* path at a throwaway temp directory instead of the
        // real /etc/nginx - these tests assert on file writes, not on
        // whether nginx/systemctl are actually installed (they aren't, on
        // this Windows dev box, and don't need to be for this to be valid).
        $this->tempRoot = sys_get_temp_dir().'/mtp-deploy-test-'.uniqid();

        config([
            'mtp.nginx_sites_available_path' => $this->tempRoot.'/sites-available',
            'mtp.nginx_sites_enabled_path' => $this->tempRoot.'/sites-enabled',
            'mtp.sites_root' => $this->tempRoot.'/www',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_provisioning_writes_the_vhost_and_creates_the_document_root(): void
    {
        $website = $this->makeWebsite();

        app(WebsiteProvisioningService::class)->provision($website);

        $this->assertFileExists($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertDirectoryExists($website->publicPath());

        $config = File::get($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertStringContainsString('server_name example.com;', $config);
    }

    public function test_provisioning_creates_a_symlink_in_sites_enabled(): void
    {
        $website = $this->makeWebsite();

        app(WebsiteProvisioningService::class)->provision($website);

        $enabledPath = $this->tempRoot.'/sites-enabled/example.com.conf';

        $this->assertTrue(is_link($enabledPath) || File::exists($enabledPath));
    }

    public function test_deprovisioning_removes_the_vhost_and_symlink(): void
    {
        $website = $this->makeWebsite();
        $provisioning = app(WebsiteProvisioningService::class);

        $provisioning->provision($website);
        $this->assertFileExists($this->tempRoot.'/sites-available/example.com.conf');

        $provisioning->deprovision($website);

        $this->assertFileDoesNotExist($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertFileDoesNotExist($this->tempRoot.'/sites-enabled/example.com.conf');
    }

    public function test_republish_regenerates_the_vhost_reflecting_current_status(): void
    {
        $website = $this->makeWebsite();
        $provisioning = app(WebsiteProvisioningService::class);

        $provisioning->provision($website);

        $website->update(['status' => WebsiteStatus::Suspended]);
        $provisioning->republish($website->fresh());

        $config = File::get($this->tempRoot.'/sites-available/example.com.conf');
        $this->assertStringContainsString('return 503', $config);
    }

    public function test_republish_recreates_a_missing_document_root(): void
    {
        // Covers a real gap found in production: an earlier `provision()`
        // call can fail to create the document root (e.g. a permissions
        // issue that gets fixed later), leaving a website record with no
        // directory on disk. Since there's no separate "retry provisioning"
        // action - just the regular Save button, which calls republish() -
        // republish() must also be able to recreate it, not just rewrite
        // the vhost.
        $website = $this->makeWebsite();

        $this->assertDirectoryDoesNotExist($website->publicPath());

        app(WebsiteProvisioningService::class)->republish($website);

        $this->assertDirectoryExists($website->publicPath());
    }

    private function makeWebsite(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.com',
            'aliases' => [],
            'document_root' => $this->tempRoot.'/www/example.com',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);
    }
}
