<?php

declare(strict_types=1);

namespace App\DTOs\AiAssistant;

final readonly class AiAssistantResult
{
    public function __construct(
        public bool $successful,
        public string $text = '',
        public ?string $error = null,
    ) {}
}
