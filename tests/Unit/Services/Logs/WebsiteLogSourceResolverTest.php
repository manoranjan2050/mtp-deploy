<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Logs;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use App\Services\Logs\WebsiteLogSourceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteLogSourceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_laravel_website_gets_all_three_log_sources(): void
    {
        $website = $this->website(WebsiteFramework::Laravel);

        $sources = app(WebsiteLogSourceResolver::class)->sources($website);

        $this->assertArrayHasKey('nginx access log', $sources);
        $this->assertArrayHasKey('nginx error log', $sources);
        $this->assertArrayHasKey('Laravel log', $sources);
        $this->assertStringContainsString('storage/logs/laravel.log', $sources['Laravel log']);
    }

    public function test_a_static_website_only_gets_nginx_logs(): void
    {
        $website = $this->website(WebsiteFramework::Static);

        $sources = app(WebsiteLogSourceResolver::class)->sources($website);

        $this->assertArrayHasKey('nginx access log', $sources);
        $this->assertArrayNotHasKey('Laravel log', $sources);
    }

    public function test_nginx_log_paths_use_the_configured_log_directory(): void
    {
        config(['mtp.nginx_log_path' => '/custom/nginx/logs']);

        $website = $this->website(WebsiteFramework::Laravel);

        $sources = app(WebsiteLogSourceResolver::class)->sources($website);

        $this->assertSame('/custom/nginx/logs/example.test-access.log', $sources['nginx access log']);
    }

    private function website(WebsiteFramework $framework): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => '/var/www/example.test',
            'php_version' => '8.3',
            'framework' => $framework,
        ]);
    }
}
