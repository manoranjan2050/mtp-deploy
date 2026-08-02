<?php

declare(strict_types=1);

namespace App\DTOs\Databases;

final readonly class CreateDatabaseData
{
    public function __construct(
        public int $serverId,
        public string $name,
        public ?int $websiteId = null,
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci',
        public ?int $createdBy = null,
    ) {}
}
