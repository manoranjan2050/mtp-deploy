<?php

declare(strict_types=1);

namespace App\Actions\Servers;

use App\DTOs\System\SystemCommandResult;
use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\Servers\SshConnectionService;

class TestServerConnectionAction
{
    public function __construct(
        private readonly SshConnectionService $ssh,
    ) {}

    public function handle(Server $server): SystemCommandResult
    {
        $result = $this->ssh->testConnection($server);

        $server->update([
            'status' => $result->successful ? ServerStatus::Connected : ServerStatus::Unreachable,
            'last_connected_at' => $result->successful ? now() : $server->last_connected_at,
            'os' => $result->successful ? $result->output : $server->os,
        ]);

        activity('server')
            ->causedBy(auth()->user())
            ->performedOn($server)
            ->withProperties(['successful' => $result->successful])
            ->log($result->successful ? 'connected to server' : 'failed to connect to server');

        return $result;
    }
}
