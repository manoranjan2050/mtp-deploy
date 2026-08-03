<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\DTOs\System\SystemCommandResult;
use App\Models\QueueWorker;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Writes the real Supervisor program config file (genuine file I/O,
 * meaningful on any OS, tests point it at a temp directory rather than the
 * real /etc/supervisor/conf.d - same pattern as
 * WebsiteProvisioningServiceTest in Module 3), then calls the real
 * `supervisorctl` binary to actually apply it. There is no `supervisorctl`
 * on this Windows dev box, so those calls honestly fail here - the same
 * "never fake server state" principle as Module 2's SystemMetricsService.
 */
class SupervisorProcessService
{
    public function __construct(
        private readonly SupervisorConfigGeneratorService $generator,
    ) {}

    public function writeConfig(QueueWorker $worker): void
    {
        File::ensureDirectoryExists(config('mtp.supervisor_config_path'));
        File::put($this->configPath($worker), $this->generator->generate($worker));
    }

    public function removeConfig(QueueWorker $worker): void
    {
        File::delete($this->configPath($worker));
    }

    public function reloadSupervisor(): SystemCommandResult
    {
        $reread = $this->run(['supervisorctl', 'reread']);

        if (! $reread->successful) {
            return $reread;
        }

        return $this->run(['supervisorctl', 'update']);
    }

    public function start(QueueWorker $worker): SystemCommandResult
    {
        return $this->run(['supervisorctl', 'start', "{$worker->supervisor_program_name}:*"]);
    }

    public function stop(QueueWorker $worker): SystemCommandResult
    {
        return $this->run(['supervisorctl', 'stop', "{$worker->supervisor_program_name}:*"]);
    }

    public function restart(QueueWorker $worker): SystemCommandResult
    {
        return $this->run(['supervisorctl', 'restart', "{$worker->supervisor_program_name}:*"]);
    }

    private function configPath(QueueWorker $worker): string
    {
        return rtrim((string) config('mtp.supervisor_config_path'), '/').'/'.$worker->supervisor_program_name.'.conf';
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): SystemCommandResult
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        return new SystemCommandResult(
            successful: $process->isSuccessful(),
            exitCode: $process->getExitCode(),
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
