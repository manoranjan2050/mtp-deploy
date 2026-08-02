<?php

declare(strict_types=1);

namespace App\DTOs\System;

final readonly class SystemCommandResult
{
    public function __construct(
        public bool $successful,
        public ?int $exitCode,
        public string $output,
        public string $errorOutput,
    ) {}
}
