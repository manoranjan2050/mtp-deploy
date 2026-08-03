<?php

declare(strict_types=1);

namespace App\DTOs\Terminal;

use App\Enums\TerminalCommandStatus;

final readonly class TerminalCommandResult
{
    public function __construct(
        public TerminalCommandStatus $status,
        public string $output,
        public ?int $exitCode,
        public string $currentDirectory,
        public bool $requiresConfirmation = false,
    ) {}
}
