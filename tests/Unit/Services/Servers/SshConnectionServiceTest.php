<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Servers;

use App\Models\Server;
use App\Services\Servers\SshConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

/**
 * Real phpseclib3 SSH2 client - no mocking. This sandbox has no real remote
 * SSH server to connect to, so only the honest-failure path (an unreachable
 * host) is exercised, the same "never fake server state" principle as
 * SystemMetricsService/SystemCrontabService. A real remote server is needed
 * for one manual smoke test of the success path.
 */
class SshConnectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privateKey = (string) RSA::createKey(2048)->toString('PKCS1');
    }

    public function test_connecting_to_an_unreachable_host_honestly_fails(): void
    {
        $server = $this->server(['ssh_host' => '127.0.0.1', 'ssh_port' => 1]);

        $result = app(SshConnectionService::class)->testConnection($server);

        $this->assertFalse($result->successful);
        $this->assertNotEmpty($result->errorOutput);
    }

    public function test_run_on_an_unreachable_host_honestly_fails(): void
    {
        $server = $this->server(['ssh_host' => '127.0.0.1', 'ssh_port' => 1]);

        $result = app(SshConnectionService::class)->run($server, ['echo', 'hi']);

        $this->assertFalse($result->successful);
        $this->assertNotEmpty($result->errorOutput);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function server(array $overrides = []): Server
    {
        return Server::query()->create(array_merge([
            'name' => 'Remote Test Server',
            'ssh_host' => 'example.invalid',
            'ssh_port' => 22,
            'ssh_user' => 'deploy',
            'ssh_private_key' => $this->privateKey,
        ], $overrides));
    }
}
