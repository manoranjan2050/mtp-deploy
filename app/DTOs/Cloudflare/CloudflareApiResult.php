<?php

declare(strict_types=1);

namespace App\DTOs\Cloudflare;

final readonly class CloudflareApiResult
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public bool $successful,
        public mixed $data = null,
        public array $errors = [],
    ) {}
}
