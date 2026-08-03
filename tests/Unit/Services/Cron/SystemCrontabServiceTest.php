<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cron;

use App\Models\Server;
use App\Services\Cron\SystemCrontabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There is no `crontab` binary on this Windows dev box, so a real sync
 * genuinely fails here - the same honesty principle as SystemMetricsService
 * (Module 2) reporting "unsupported" off-Linux rather than faking success.
 * A real Linux server has a working `crontab` command and this genuinely
 * edits it (see CrontabContentBuilderTest for the content-generation logic,
 * which is fully OS-independent and tested for real).
 */
class SystemCrontabServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_honestly_reports_failure_when_no_crontab_binary_exists(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This assertion is specific to environments without a crontab binary.');
        }

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        $result = app(SystemCrontabService::class)->sync($server);

        $this->assertFalse($result->successful);
    }
}
