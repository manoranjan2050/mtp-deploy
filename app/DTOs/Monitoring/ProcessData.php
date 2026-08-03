<?php

declare(strict_types=1);

namespace App\DTOs\Monitoring;

final readonly class ProcessData
{
    public function __construct(
        public int $pid,
        public int $ppid,
        public float $cpuPercent,
        public float $memoryPercent,
        public string $elapsedTime,
        public string $command,
    ) {}
}
