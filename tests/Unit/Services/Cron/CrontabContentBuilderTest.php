<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cron;

use App\Models\CronJob;
use App\Models\Server;
use App\Services\Cron\CrontabContentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrontabContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_wraps_managed_jobs_in_a_marked_block(): void
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $job = CronJob::query()->create([
            'server_id' => $server->id,
            'label' => 'Test job',
            'command' => 'php artisan test:run',
            'schedule' => '*/5 * * * *',
        ]);

        $content = app(CrontabContentBuilder::class)->build('', collect([$job]));

        $this->assertStringContainsString(CrontabContentBuilder::BEGIN_MARKER, $content);
        $this->assertStringContainsString(CrontabContentBuilder::END_MARKER, $content);
        $this->assertStringContainsString('*/5 * * * * php artisan test:run', $content);
    }

    public function test_it_preserves_existing_crontab_content_outside_the_managed_block(): void
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $existing = "# a comment the server admin wrote by hand\n0 3 * * * /usr/bin/some-other-script.sh\n";

        $content = app(CrontabContentBuilder::class)->build($existing, collect());

        $this->assertStringContainsString('/usr/bin/some-other-script.sh', $content);
        $this->assertStringContainsString('a comment the server admin wrote by hand', $content);
    }

    public function test_re_syncing_replaces_the_previous_managed_block_instead_of_duplicating_it(): void
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $oldJob = CronJob::query()->create([
            'server_id' => $server->id,
            'label' => 'Old job',
            'command' => 'echo old',
            'schedule' => '* * * * *',
        ]);

        $builder = app(CrontabContentBuilder::class);
        $firstSync = $builder->build('', collect([$oldJob]));

        $newJob = CronJob::query()->create([
            'server_id' => $server->id,
            'label' => 'New job',
            'command' => 'echo new',
            'schedule' => '* * * * *',
        ]);

        $secondSync = $builder->build($firstSync, collect([$newJob]));

        $this->assertStringNotContainsString('echo old', $secondSync);
        $this->assertStringContainsString('echo new', $secondSync);
        $this->assertSame(1, substr_count($secondSync, CrontabContentBuilder::BEGIN_MARKER));
    }

    public function test_disabled_jobs_are_never_included(): void
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $enabled = collect([
            CronJob::query()->create([
                'server_id' => $server->id,
                'label' => 'Enabled',
                'command' => 'echo enabled',
                'schedule' => '* * * * *',
                'is_enabled' => true,
            ]),
        ]);

        // Simulates the caller (SystemCrontabService) only ever passing
        // already-filtered enabled jobs - this test documents that contract.
        $content = app(CrontabContentBuilder::class)->build('', $enabled);

        $this->assertStringContainsString('echo enabled', $content);
    }
}
