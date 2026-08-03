<?php

declare(strict_types=1);

namespace Tests\Feature\Cron;

use App\Filament\Pages\CronJobs;
use App\Models\CronJob;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CronJobsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }

    public function test_an_admin_can_create_run_and_delete_a_cron_job(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $component = Livewire::actingAs($admin)
            ->test(CronJobs::class)
            ->assertSuccessful()
            ->set('label', 'Test job')
            ->set('command', 'echo hi')
            ->set('schedule', '*/5 * * * *')
            ->call('createJob')
            ->assertSee('Test job');

        $this->assertDatabaseHas('cron_jobs', ['label' => 'Test job']);

        $jobId = CronJob::query()->firstOrFail()->id;

        $component->call('runJobNow', $jobId);
        $this->assertSame(0, CronJob::find($jobId)->last_exit_code);

        $component->call('deleteJob', $jobId);
        $this->assertDatabaseMissing('cron_jobs', ['id' => $jobId]);
    }

    public function test_an_invalid_cron_expression_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CronJobs::class)
            ->set('label', 'Bad job')
            ->set('command', 'echo hi')
            ->set('schedule', 'not a cron expression')
            ->call('createJob')
            ->assertHasErrors(['schedule']);

        $this->assertDatabaseMissing('cron_jobs', ['label' => 'Bad job']);
    }

    public function test_a_developer_cannot_access_the_page(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        Livewire::actingAs($developer)
            ->test(CronJobs::class)
            ->assertForbidden();
    }
}
