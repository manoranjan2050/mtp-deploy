<?php

declare(strict_types=1);

namespace App\DTOs\Databases;

final readonly class CreateDatabaseUserData
{
    public function __construct(
        public int $serverId,
        public string $username,
        public string $password,
        public string $host = '%',
        public ?int $createdBy = null,
    ) {}
}
