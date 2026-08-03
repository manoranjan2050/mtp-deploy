<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTOs\Monitoring\ProcessData;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

/**
 * Real `ps` output, parsed - not a placeholder. Honestly unsupported off
 * Linux (no `ps` binary in the same shape on Windows), same "never fake
 * server state" principle as `SystemMetricsService`.
 */
class ProcessListService
{
    public function isSupported(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    /**
     * @return Collection<int, ProcessData>
     */
    public function list(int $limit = 30): Collection
    {
        if (! $this->isSupported()) {
            return collect();
        }

        $process = new Process([
            'ps', '-eo', 'pid,ppid,pcpu,pmem,etime,comm', '--sort=-pcpu', '--no-headers',
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            return collect();
        }

        $lines = array_filter(explode("\n", trim($process->getOutput())));

        return collect($lines)
            ->take($limit)
            ->map(function (string $line): ?ProcessData {
                $fields = preg_split('/\s+/', trim($line), 6);

                if ($fields === false || count($fields) < 6) {
                    return null;
                }

                return new ProcessData(
                    pid: (int) $fields[0],
                    ppid: (int) $fields[1],
                    cpuPercent: (float) $fields[2],
                    memoryPercent: (float) $fields[3],
                    elapsedTime: $fields[4],
                    command: $fields[5],
                );
            })
            ->filter()
            ->values();
    }
}
