<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Enums\ServiceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Checks the status of services the panel depends on / manages. Database and
 * Redis checks are real connectivity probes and work on any OS; nginx and the
 * Cloudflare Tunnel are Linux-only production services checked via process
 * lookup, so they report Unavailable rather than a guessed status when the
 * OS can't be probed this way (this dev machine is Windows).
 */
class ServiceStatusService
{
    /**
     * @return array<string, ServiceStatus>
     */
    public function checkAll(): array
    {
        return [
            'mariadb' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'nginx' => $this->checkProcessRunning('nginx'),
            'cloudflare_tunnel' => $this->checkProcessRunning('cloudflared'),
        ];
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    private function checkDatabase(): ServiceStatus
    {
        try {
            DB::connection()->getPdo();

            return ServiceStatus::Running;
        } catch (Throwable) {
            return ServiceStatus::Stopped;
        }
    }

    private function checkRedis(): ServiceStatus
    {
        try {
            Redis::connection()->ping();

            return ServiceStatus::Running;
        } catch (Throwable) {
            return ServiceStatus::Stopped;
        }
    }

    private function checkProcessRunning(string $processName): ServiceStatus
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return ServiceStatus::Unavailable;
        }

        try {
            $process = new Process(['pgrep', '-x', $processName]);
            $process->run();

            return $process->isSuccessful() ? ServiceStatus::Running : ServiceStatus::Stopped;
        } catch (Throwable) {
            return ServiceStatus::Unavailable;
        }
    }
}
