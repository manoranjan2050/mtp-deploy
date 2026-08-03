<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cron;

use App\Models\CronJob;
use App\Models\Server;
use App\Services\Cron\CronJobRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Runs a real process (echo/exit both work identically on cmd.exe and a
 * POSIX shell), same "real infrastructure over mocks" pattern as
 * TerminalCommandServiceTest (Module 8).
 */
class CronJobRunnerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_executes_the_real_command_and_records_the_result(): void
    {
        $job = $this->job('echo hello-from-cron');

        app(CronJobRunnerService::class)->run($job);

        $job->refresh();
        $this->assertNotNull($job->last_run_at);
        $this->assertSame(0, $job->last_exit_code);
        $this->assertStringContainsString('hello-from-cron', $job->last_output);
    }

    public function test_it_records_a_non_zero_exit_code(): void
    {
        $job = $this->job('exit 7');

        app(CronJobRunnerService::class)->run($job);

        $this->assertSame(7, $job->fresh()->last_exit_code);
    }

    private function job(string $command): CronJob
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return CronJob::query()->create([
            'server_id' => $server->id,
            'label' => 'Test job',
            'command' => $command,
            'schedule' => '* * * * *',
        ]);
    }
}
