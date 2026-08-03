<?php

declare(strict_types=1);

namespace App\Services\Servers;

use App\DTOs\System\SystemCommandResult;
use App\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

/**
 * Real SSH connectivity via phpseclib3 - a genuine SSH2 client, not a
 * placeholder. Unlike shelling out to the `ssh` binary (Module 8's
 * Terminal), phpseclib is pure PHP and needs no system SSH client
 * installed, so it works identically on this Windows dev box and a real
 * Linux control-plane server. This sandbox has no real remote SSH server to
 * connect to, so only the honest-failure path (unreachable host, wrong
 * credentials) is exercised in tests - the same "never fake server state"
 * principle as SystemMetricsService/SystemCrontabService. A real remote
 * server should be used for one manual smoke test before this module is
 * considered production-ready for a genuine multi-server fleet.
 */
class SshConnectionService
{
    public function connect(Server $server, int $timeoutSeconds = 10): SSH2
    {
        $ssh = new SSH2($server->ssh_host, (int) $server->ssh_port);
        $ssh->setTimeout($timeoutSeconds);

        $key = PublicKeyLoader::load($server->ssh_private_key);

        if (! $ssh->login($server->ssh_user, $key)) {
            throw new SshConnectionException("Could not authenticate as {$server->ssh_user}@{$server->ssh_host}.");
        }

        return $ssh;
    }

    public function testConnection(Server $server): SystemCommandResult
    {
        try {
            $ssh = $this->connect($server);
            $output = (string) $ssh->exec('uname -a');
            $successful = ! $ssh->isTimeout() && $ssh->getExitStatus() === 0;

            return new SystemCommandResult(
                successful: $successful,
                exitCode: $ssh->getExitStatus(),
                output: trim($output),
                errorOutput: $successful ? '' : 'Remote command did not exit successfully.',
            );
        } catch (Throwable $exception) {
            return new SystemCommandResult(
                successful: false,
                exitCode: null,
                output: '',
                errorOutput: $exception->getMessage(),
            );
        }
    }

    /**
     * @param  list<string>  $command
     */
    public function run(Server $server, array $command): SystemCommandResult
    {
        try {
            $ssh = $this->connect($server);
            $output = (string) $ssh->exec($this->escapeCommand($command));
            $successful = ! $ssh->isTimeout() && $ssh->getExitStatus() === 0;

            return new SystemCommandResult(
                successful: $successful,
                exitCode: $ssh->getExitStatus(),
                output: $output,
                errorOutput: $successful ? '' : 'Remote command exited with a non-zero status.',
            );
        } catch (Throwable $exception) {
            return new SystemCommandResult(
                successful: false,
                exitCode: null,
                output: '',
                errorOutput: $exception->getMessage(),
            );
        }
    }

    /**
     * @param  list<string>  $command
     */
    private function escapeCommand(array $command): string
    {
        return implode(' ', array_map(escapeshellarg(...), $command));
    }
}
