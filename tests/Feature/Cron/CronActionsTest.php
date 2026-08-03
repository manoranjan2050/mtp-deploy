<?php

declare(strict_types=1);

namespace Tests\Feature\Cron;

use App\Actions\Cron\CreateCronJobAction;
use App\Actions\Cron\DeleteCronJobAction;
use App\Actions\Cron\RunCronJobNowAction;
use App\Actions\Cron\ToggleCronJobAction;
use App\DTOs\Cron\CronJobData;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_action_creates_a_job_and_logs_activity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        $job = app(CreateCronJobAction::class)->handle(new CronJobData(
            serverId: $server->id,
            websiteId: null,
            label: 'Test job',
            command: 'echo hi',
            schedule: '* * * * *',
            createdBy: $user->id,
        ));

        $this->assertDatabaseHas('cron_jobs', ['id' => $job->id, 'label' => 'Test job']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'cron', 'description' => 'created cron job']);
    }

    public function test_toggle_action_flips_is_enabled(): void
    {
        $this->actingAs(User::factory()->create());
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $job = app(CreateCronJobAction::class)->handle(new CronJobData(
            serverId: $server->id,
            websiteId: null,
            label: 'Test job',
            command: 'echo hi',
            schedule: '* * * * *',
        ));

        $this->assertTrue($job->is_enabled);

        $toggled = app(ToggleCronJobAction::class)->handle($job);

        $this->assertFalse($toggled->is_enabled);
    }

    public function test_run_now_action_executes_the_real_command(): void
    {
        $this->actingAs(User::factory()->create());
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $job = app(CreateCronJobAction::class)->handle(new CronJobData(
            serverId: $server->id,
            websiteId: null,
            label: 'Test job',
            command: 'echo hello-cron-action',
            schedule: '* * * * *',
        ));

        $ranJob = app(RunCronJobNowAction::class)->handle($job);

        $this->assertSame(0, $ranJob->last_exit_code);
        $this->assertStringContainsString('hello-cron-action', $ranJob->last_output);
    }

    public function test_delete_action_removes_the_job(): void
    {
        $this->actingAs(User::factory()->create());
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $job = app(CreateCronJobAction::class)->handle(new CronJobData(
            serverId: $server->id,
            websiteId: null,
            label: 'Test job',
            command: 'echo hi',
            schedule: '* * * * *',
        ));

        app(DeleteCronJobAction::class)->handle($job);

        $this->assertDatabaseMissing('cron_jobs', ['id' => $job->id]);
    }
}
