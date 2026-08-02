<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System;

use App\Services\System\SystemMetricsService;
use Tests\TestCase;

class SystemMetricsServiceTest extends TestCase
{
    public function test_capture_reports_unsupported_on_non_linux_hosts(): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $this->markTestSkipped('This assertion only applies off Linux; see the Linux-path test below.');
        }

        $data = (new SystemMetricsService)->capture();

        $this->assertFalse($data->isSupported);
        $this->assertNull($data->cpuUsagePercent);
        $this->assertNull($data->memoryUsedBytes);
    }

    public function test_capture_reports_real_metrics_on_linux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Requires a Linux host to read /proc.');
        }

        $data = (new SystemMetricsService)->capture();

        $this->assertTrue($data->isSupported);
        $this->assertNotNull($data->memoryTotalBytes);
        $this->assertGreaterThan(0, $data->memoryTotalBytes);
    }
}
