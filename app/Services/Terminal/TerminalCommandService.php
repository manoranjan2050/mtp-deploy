<?php

declare(strict_types=1);

namespace App\Services\Terminal;

use App\DTOs\Terminal\TerminalCommandResult;
use App\Enums\TerminalCommandStatus;
use App\Models\TerminalCommand;
use App\Models\TerminalSession;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Executes one command at a time against a TerminalSession's current working
 * directory, persisting every submission (executed or blocked) to
 * `terminal_commands` for history/audit. This is deliberately **not** a true
 * interactive PTY - there's no persisted shell environment (exported
 * variables don't survive between commands) and no live keystroke echo. `cd`
 * is special-cased to update the session's `current_directory` between
 * one-shot process runs, which is what makes it feel like a normal shell
 * despite each command being its own fresh process. See CLAUDE.md for why
 * this scope was chosen over a real WebSocket/PTY bridge.
 */
class TerminalCommandService
{
    public function __construct(
        private readonly DangerousCommandGuard $guard,
    ) {}

    public function execute(TerminalSession $session, int $userId, string $rawCommand, bool $confirmed = false): TerminalCommandResult
    {
        $command = trim($rawCommand);

        if ($command === '') {
            return new TerminalCommandResult(
                status: TerminalCommandStatus::Executed,
                output: '',
                exitCode: 0,
                currentDirectory: $session->current_directory,
            );
        }

        if (preg_match('/^cd(\s+(.*))?$/i', $command, $matches) === 1) {
            return $this->changeDirectory($session, $userId, $command, trim($matches[2] ?? ''));
        }

        if ($this->guard->isDangerous($command) && ! $confirmed) {
            $this->record($session, $userId, $command, TerminalCommandStatus::Blocked, 'Blocked: this looks like a destructive command. Confirm to run it anyway.', null);

            return new TerminalCommandResult(
                status: TerminalCommandStatus::Blocked,
                output: 'Blocked: this looks like a destructive command. Confirm to run it anyway.',
                exitCode: null,
                currentDirectory: $session->current_directory,
                requiresConfirmation: true,
            );
        }

        $process = Process::fromShellCommandline($command, cwd: $session->current_directory);
        $process->setTimeout((int) config('mtp.terminal_command_timeout', 30));

        try {
            $process->run();
            $output = $process->getOutput().$process->getErrorOutput();
            $exitCode = $process->getExitCode();
        } catch (ProcessTimedOutException) {
            $output = "Command timed out after {$process->getTimeout()}s and was stopped.";
            $exitCode = -1;
        }

        $this->record($session, $userId, $command, TerminalCommandStatus::Executed, $output, $exitCode);

        return new TerminalCommandResult(
            status: TerminalCommandStatus::Executed,
            output: $output,
            exitCode: $exitCode,
            currentDirectory: $session->current_directory,
        );
    }

    private function changeDirectory(TerminalSession $session, int $userId, string $rawCommand, string $target): TerminalCommandResult
    {
        $destination = match (true) {
            $target === '' => (string) config('mtp.terminal_default_directory'),
            $this->isAbsolute($target) => $target,
            default => rtrim($session->current_directory, '/\\').'/'.$target,
        };

        $real = realpath($destination);

        if ($real === false || ! is_dir($real)) {
            $output = "cd: no such directory: {$target}";
            $this->record($session, $userId, $rawCommand, TerminalCommandStatus::Executed, $output, 1);

            return new TerminalCommandResult(
                status: TerminalCommandStatus::Executed,
                output: $output,
                exitCode: 1,
                currentDirectory: $session->current_directory,
            );
        }

        $session->update(['current_directory' => $real]);

        $this->record($session, $userId, $rawCommand, TerminalCommandStatus::Executed, '', 0);

        return new TerminalCommandResult(
            status: TerminalCommandStatus::Executed,
            output: '',
            exitCode: 0,
            currentDirectory: $real,
        );
    }

    private function isAbsolute(string $path): bool
    {
        return (bool) preg_match('#^([a-zA-Z]:[\\\\/]|[\\\\/])#', $path);
    }

    private function record(TerminalSession $session, int $userId, string $command, TerminalCommandStatus $status, string $output, ?int $exitCode): void
    {
        TerminalCommand::query()->create([
            'terminal_session_id' => $session->id,
            'user_id' => $userId,
            'command' => $command,
            'output' => $output,
            'exit_code' => $exitCode,
            'status' => $status,
            'executed_at' => now(),
        ]);
    }
}
