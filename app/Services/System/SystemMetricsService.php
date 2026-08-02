<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTOs\System\SystemMetricsData;
use Illuminate\Support\Carbon;

/**
 * Reads live host metrics from /proc on Linux. Symfony Process is not needed
 * here - these are plain file reads, not privileged commands (see
 * docs/Architecture.md's privileged-command model, which this does not
 * fall under).
 */
class SystemMetricsService
{
    public function capture(): SystemMetricsData
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return SystemMetricsData::unsupported();
        }

        return new SystemMetricsData(
            isSupported: true,
            cpuUsagePercent: $this->readCpuUsagePercent(),
            memoryUsedBytes: $this->readMemory()['used'] ?? null,
            memoryTotalBytes: $this->readMemory()['total'] ?? null,
            diskUsedBytes: $this->readDisk()['used'] ?? null,
            diskTotalBytes: $this->readDisk()['total'] ?? null,
            load1min: $this->readLoadAverage()[0] ?? null,
            load5min: $this->readLoadAverage()[1] ?? null,
            load15min: $this->readLoadAverage()[2] ?? null,
            networkRxBytes: $this->readNetwork()['rx'] ?? null,
            networkTxBytes: $this->readNetwork()['tx'] ?? null,
            collectedAt: Carbon::now(),
        );
    }

    /**
     * Two /proc/stat samples 100ms apart, deltas over total jiffies.
     */
    private function readCpuUsagePercent(): ?float
    {
        $first = $this->readCpuJiffies();

        if ($first === null) {
            return null;
        }

        usleep(100_000);

        $second = $this->readCpuJiffies();

        if ($second === null) {
            return null;
        }

        $totalDelta = $second['total'] - $first['total'];
        $idleDelta = $second['idle'] - $first['idle'];

        if ($totalDelta <= 0) {
            return null;
        }

        return round((1 - ($idleDelta / $totalDelta)) * 100, 1);
    }

    /**
     * @return array{total: int, idle: int}|null
     */
    private function readCpuJiffies(): ?array
    {
        $line = @file('/proc/stat')[0] ?? null;

        if (! $line) {
            return null;
        }

        $fields = array_values(array_filter(explode(' ', trim(preg_replace('/^cpu\s+/', '', $line)))));
        $fields = array_map('intval', $fields);

        if (count($fields) < 4) {
            return null;
        }

        return [
            'total' => array_sum($fields),
            'idle' => $fields[3],
        ];
    }

    /**
     * @return array{used: int, total: int}|null
     */
    private function readMemory(): ?array
    {
        $content = @file_get_contents('/proc/meminfo');

        if ($content === false) {
            return null;
        }

        preg_match('/MemTotal:\s+(\d+) kB/', $content, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+) kB/', $content, $availableMatch);

        if (! $totalMatch || ! $availableMatch) {
            return null;
        }

        $totalBytes = ((int) $totalMatch[1]) * 1024;
        $availableBytes = ((int) $availableMatch[1]) * 1024;

        return [
            'total' => $totalBytes,
            'used' => $totalBytes - $availableBytes,
        ];
    }

    /**
     * @return array{used: int, total: int}|null
     */
    private function readDisk(): ?array
    {
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');

        if ($total === false || $free === false) {
            return null;
        }

        return [
            'total' => (int) $total,
            'used' => (int) $total - (int) $free,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float}|null
     */
    private function readLoadAverage(): ?array
    {
        $load = sys_getloadavg();

        return $load === false ? null : $load;
    }

    /**
     * Sums RX/TX across all non-loopback interfaces from /proc/net/dev.
     *
     * @return array{rx: int, tx: int}|null
     */
    private function readNetwork(): ?array
    {
        $lines = @file('/proc/net/dev');

        if ($lines === false) {
            return null;
        }

        $rx = 0;
        $tx = 0;

        foreach (array_slice($lines, 2) as $line) {
            [$iface, $rest] = array_pad(explode(':', trim($line), 2), 2, null);

            if ($rest === null || str_starts_with(trim($iface), 'lo')) {
                continue;
            }

            $fields = array_values(array_filter(preg_split('/\s+/', trim($rest))));

            if (count($fields) < 9) {
                continue;
            }

            $rx += (int) $fields[0];
            $tx += (int) $fields[8];
        }

        return ['rx' => $rx, 'tx' => $tx];
    }
}
