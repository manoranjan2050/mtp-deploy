<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\DTOs\System\SystemCommandResult;
use App\Models\Server;
use Symfony\Component\Process\Process;

/**
 * Real `crontab -l`/`crontab -` invocations against this server's actual
 * system crontab - not a placeholder. There is no `crontab` binary on this
 * Windows dev box, so this honestly reports failure here, the same
 * "never fake server state" principle as SystemMetricsService (Module 2)
 * being honest about /proc not existing off-Linux. A real Linux server has
 * a working `crontab` command and this genuinely edits it.
 */
class SystemCrontabService
{
    public function __construct(
        private readonly CrontabContentBuilder $builder,
    ) {}

    public function sync(Server $server): SystemCommandResult
    {
        $listProcess = new Process(['crontab', '-l']);
        $listProcess->run();

        // `crontab -l` exits non-zero when no crontab exists yet for this
        // user - that's an expected "start from empty," not a real failure,
        // so it's not treated as one here.
        $existing = $listProcess->isSuccessful() ? $listProcess->getOutput() : '';

        $enabledJobs = $server->cronJobs()->where('is_enabled', true)->get();
        $newContent = $this->builder->build($existing, $enabledJobs);

        $writeProcess = new Process(['crontab', '-']);
        $writeProcess->setInput($newContent);
        $writeProcess->run();

        return new SystemCommandResult(
            successful: $writeProcess->isSuccessful(),
            exitCode: $writeProcess->getExitCode(),
            output: $writeProcess->getOutput(),
            errorOutput: $writeProcess->getErrorOutput(),
        );
    }
}
