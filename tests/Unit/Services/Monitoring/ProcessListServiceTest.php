<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Monitoring;

use App\Services\Monitoring\ProcessListService;
use Tests\TestCase;

/**
 * Real `ps` output on Linux - unsupported off Linux (this dev box), same
 * honest-failure convention as SystemMetricsServiceTest.
 */
class ProcessListServiceTest extends TestCase
{
    public function test_is_supported_reflects_the_real_host_os(): void
    {
        $this->assertSame(PHP_OS_FAMILY === 'Linux', app(ProcessListService::class)->isSupported());
    }

    public function test_list_returns_an_empty_collection_when_unsupported(): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $this->markTestSkipped('This assertion only applies off Linux.');
        }

        $this->assertTrue(app(ProcessListService::class)->list()->isEmpty());
    }

    public function test_list_returns_real_process_rows_on_linux(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('This assertion only applies on Linux.');
        }

        $processes = app(ProcessListService::class)->list();

        $this->assertTrue($processes->isNotEmpty());
        $this->assertGreaterThan(0, $processes->first()->pid);
    }
}
