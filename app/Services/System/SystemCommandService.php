<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTOs\System\SystemCommandResult;
use App\Enums\WhitelistedOperation;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * The only class in the codebase allowed to construct a Process. Every
 * invocation is a pre-defined, whitelisted `WhitelistedOperation` - never a
 * raw string built from user input - and is logged before and after execution
 * via the activity log, per docs/Security.md. See docs/Architecture.md's
 * "Privileged System Operations" section for the full model, including how
 * this is granted a narrow `sudoers` entry in production.
 */
class SystemCommandService
{
    /**
     * @param  array<string, string>  $arguments
     */
    public function run(WhitelistedOperation $operation, array $arguments = []): SystemCommandResult
    {
        $command = $operation->commandFor($arguments);

        activity('system-command')
            ->causedBy(Auth::user())
            ->withProperties(['operation' => $operation->value, 'arguments' => $arguments])
            ->log('about to run a privileged system command');

        $process = new Process($command);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $result = new SystemCommandResult(
                successful: false,
                exitCode: null,
                output: '',
                errorOutput: 'Command timed out: '.$exception->getMessage(),
            );

            $this->logResult($operation, $arguments, $result);

            return $result;
        }

        $result = new SystemCommandResult(
            successful: $process->isSuccessful(),
            exitCode: $process->getExitCode(),
            output: $this->truncate($process->getOutput()),
            errorOutput: $this->truncate($process->getErrorOutput()),
        );

        $this->logResult($operation, $arguments, $result);

        return $result;
    }

    /**
     * @param  array<string, string>  $arguments
     */
    private function logResult(WhitelistedOperation $operation, array $arguments, SystemCommandResult $result): void
    {
        activity('system-command')
            ->causedBy(Auth::user())
            ->withProperties([
                'operation' => $operation->value,
                'arguments' => $arguments,
                'successful' => $result->successful,
                'exit_code' => $result->exitCode,
                'output' => $result->output,
                'error_output' => $result->errorOutput,
            ])
            ->log($result->successful ? 'privileged system command succeeded' : 'privileged system command failed');
    }

    private function truncate(string $output, int $limit = 4000): string
    {
        return mb_strlen($output) > $limit ? mb_substr($output, 0, $limit).'... (truncated)' : $output;
    }
}
