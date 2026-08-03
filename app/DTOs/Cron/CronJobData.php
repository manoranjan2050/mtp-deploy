<?php

declare(strict_types=1);

namespace App\DTOs\Cron;

final readonly class CronJobData
{
    public function __construct(
        public int $serverId,
        public ?int $websiteId,
        public string $label,
        public string $command,
        public string $schedule,
        public bool $isEnabled = true,
        public ?int $createdBy = null,
    ) {}
}
