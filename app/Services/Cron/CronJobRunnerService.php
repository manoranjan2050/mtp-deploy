<?php

declare(strict_types=1);

namespace App\Services\Cron;

use App\Models\CronJob;
use Symfony\Component\Process\Process;

/**
 * Runs a cron job's command once, immediately, on demand ("Run now" in the
 * UI) - a genuine one-shot process execution, same pattern as
 * TerminalCommandService (Module 8), not a simulation.
 */
class CronJobRunnerService
{
    public function run(CronJob $job): void
    {
        $process = Process::fromShellCommandline($job->command);
        $process->setTimeout(300);
        $process->run();

        $job->update([
            'last_run_at' => now(),
            'last_exit_code' => $process->getExitCode(),
            'last_output' => trim($process->getOutput().$process->getErrorOutput()),
        ]);
    }
}
