<?php

declare(strict_types=1);

namespace Tests\Feature\Servers;

use App\Actions\Servers\TestServerConnectionAction;
use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class TestServerConnectionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_connection_marks_the_server_unreachable(): void
    {
        $server = Server::query()->create([
            'name' => 'Remote Test Server',
            'ssh_host' => '127.0.0.1',
            'ssh_port' => 1,
            'ssh_user' => 'deploy',
            'ssh_private_key' => (string) RSA::createKey(2048)->toString('PKCS1'),
            'status' => ServerStatus::Pending,
        ]);

        $result = app(TestServerConnectionAction::class)->handle($server);

        $this->assertFalse($result->successful);
        $this->assertSame(ServerStatus::Unreachable, $server->fresh()->status);
        $this->assertNull($server->fresh()->last_connected_at);
    }

    public function test_it_logs_activity_for_a_connection_attempt(): void
    {
        $server = Server::query()->create([
            'name' => 'Remote Test Server',
            'ssh_host' => '127.0.0.1',
            'ssh_port' => 1,
            'ssh_user' => 'deploy',
            'ssh_private_key' => (string) RSA::createKey(2048)->toString('PKCS1'),
        ]);

        app(TestServerConnectionAction::class)->handle($server);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'server',
            'subject_id' => $server->id,
            'description' => 'failed to connect to server',
        ]);
    }
}
