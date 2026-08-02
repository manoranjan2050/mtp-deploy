<?php

declare(strict_types=1);

namespace App\DTOs\System;

use Illuminate\Support\Carbon;

/**
 * A single point-in-time reading of host system metrics. `isSupported` is
 * false when the current OS can't be read (e.g. local Windows dev) - callers
 * must check it rather than treating null/zero fields as "0% usage".
 */
final readonly class SystemMetricsData
{
    public function __construct(
        public bool $isSupported,
        public ?float $cpuUsagePercent,
        public ?int $memoryUsedBytes,
        public ?int $memoryTotalBytes,
        public ?int $diskUsedBytes,
        public ?int $diskTotalBytes,
        public ?float $load1min,
        public ?float $load5min,
        public ?float $load15min,
        public ?int $networkRxBytes,
        public ?int $networkTxBytes,
        public Carbon $collectedAt,
    ) {}

    public static function unsupported(): self
    {
        return new self(
            isSupported: false,
            cpuUsagePercent: null,
            memoryUsedBytes: null,
            memoryTotalBytes: null,
            diskUsedBytes: null,
            diskTotalBytes: null,
            load1min: null,
            load5min: null,
            load15min: null,
            networkRxBytes: null,
            networkTxBytes: null,
            collectedAt: now(),
        );
    }

    public function memoryUsagePercent(): ?float
    {
        if (! $this->memoryTotalBytes) {
            return null;
        }

        return round(($this->memoryUsedBytes / $this->memoryTotalBytes) * 100, 1);
    }

    public function diskUsagePercent(): ?float
    {
        if (! $this->diskTotalBytes) {
            return null;
        }

        return round(($this->diskUsedBytes / $this->diskTotalBytes) * 100, 1);
    }
}
