<?php

declare(strict_types=1);

namespace App\DTOs\Queue;

final readonly class QueueWorkerData
{
    public function __construct(
        public int $websiteId,
        public string $connection = 'database',
        public string $queue = 'default',
        public int $processes = 1,
        public ?int $createdBy = null,
    ) {}
}
