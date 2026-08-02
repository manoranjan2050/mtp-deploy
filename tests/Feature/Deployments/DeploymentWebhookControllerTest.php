<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().'/mtp-deploy-webhook-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_unknown_token_returns_404(): void
    {
        $response = $this->postJson('/api/webhooks/deploy/not-a-real-token');

        $response->assertNotFound();
    }

    public function test_auto_deploy_disabled_returns_403(): void
    {
        $website = $this->makeWebsite(['auto_deploy' => false]);

        $response = $this->postJson("/api/webhooks/deploy/{$website->webhook_token}");

        $response->assertForbidden();
    }

    public function test_wrong_signature_returns_403(): void
    {
        $website = $this->makeWebsite(['auto_deploy' => true]);

        $response = $this->postJson(
            "/api/webhooks/deploy/{$website->webhook_token}",
            ['ref' => 'refs/heads/main'],
            ['X-Hub-Signature-256' => 'sha256=not-the-right-signature'],
        );

        $response->assertForbidden();
    }

    public function test_valid_signature_triggers_a_deployment(): void
    {
        $website = $this->makeWebsite([
            'auto_deploy' => true,
            'repository_url' => $this->tempRoot.'/does-not-exist', // deploy will fail, but that's still "triggered"
        ]);

        $payload = json_encode(['ref' => 'refs/heads/main']);
        $signature = 'sha256='.hash_hmac('sha256', $payload, $website->webhook_token);

        $response = $this->call(
            'POST',
            "/api/webhooks/deploy/{$website->webhook_token}",
            server: ['HTTP_X-Hub-Signature-256' => $signature, 'CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );

        $response->assertOk();
        $this->assertDatabaseHas('deployments', ['website_id' => $website->id]);
    }

    public function test_no_signature_header_still_triggers_a_deployment(): void
    {
        $website = $this->makeWebsite([
            'auto_deploy' => true,
            'repository_url' => $this->tempRoot.'/does-not-exist',
        ]);

        $response = $this->postJson("/api/webhooks/deploy/{$website->webhook_token}");

        $response->assertOk();
        $this->assertDatabaseHas('deployments', ['website_id' => $website->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeWebsite(array $overrides = []): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create(array_merge([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example-'.uniqid().'.test',
            'document_root' => $this->tempRoot.'/site',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
            'repository_url' => $this->tempRoot.'/remote.git',
            'git_branch' => 'main',
        ], $overrides));
    }
}
