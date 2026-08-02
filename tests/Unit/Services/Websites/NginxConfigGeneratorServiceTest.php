<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Websites;

use App\Enums\WebsiteFramework;
use App\Enums\WebsiteStatus;
use App\Models\Server;
use App\Models\Website;
use App\Services\Websites\NginxConfigGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NginxConfigGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_website_generates_a_working_server_block(): void
    {
        $website = $this->makeWebsite([
            'domain' => 'example.com',
            'aliases' => ['www.example.com'],
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);

        $config = (new NginxConfigGeneratorService)->generate($website);

        $this->assertStringContainsString('server_name example.com www.example.com;', $config);
        $this->assertStringContainsString('root '.$website->publicPath().';', $config);
        $this->assertStringContainsString('fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;', $config);
        $this->assertStringNotContainsString('return 503', $config);
    }

    public function test_laravel_website_serves_from_the_public_subdirectory(): void
    {
        $website = $this->makeWebsite([
            'document_root' => '/var/www/example.com',
            'framework' => WebsiteFramework::Laravel,
        ]);

        $config = (new NginxConfigGeneratorService)->generate($website);

        $this->assertStringContainsString('root /var/www/example.com/public;', $config);
    }

    public function test_static_website_serves_from_the_document_root_directly(): void
    {
        $website = $this->makeWebsite([
            'document_root' => '/var/www/example.com',
            'framework' => WebsiteFramework::Static,
        ]);

        $config = (new NginxConfigGeneratorService)->generate($website);

        $this->assertStringContainsString('root /var/www/example.com;', $config);
        $this->assertStringNotContainsString('/public;', $config);
    }

    public function test_suspended_website_generates_a_503_block_instead_of_the_live_site(): void
    {
        $website = $this->makeWebsite(['status' => WebsiteStatus::Suspended]);

        $config = (new NginxConfigGeneratorService)->generate($website);

        $this->assertStringContainsString('return 503', $config);
        $this->assertStringNotContainsString('fastcgi_pass', $config);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeWebsite(array $overrides = []): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->make(array_merge([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.com',
            'aliases' => [],
            'document_root' => '/var/www/example.com',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
            'status' => WebsiteStatus::Active,
        ], $overrides));
    }
}
