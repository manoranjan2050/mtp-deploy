<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Updates;

use App\Services\Updates\UpdateCheckerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateCheckerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('mtp:latest-remote-commit');
    }

    public function test_current_commit_reads_the_real_local_git_head(): void
    {
        $commit = app(UpdateCheckerService::class)->currentCommit();

        $this->assertNotNull($commit);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $commit);
    }

    public function test_it_reports_no_update_when_commits_match(): void
    {
        $service = app(UpdateCheckerService::class);
        $current = $service->currentCommit();

        Http::fake([
            'api.github.com/*' => Http::response(['sha' => $current]),
        ]);

        $this->assertFalse($service->isUpdateAvailable());
    }

    public function test_it_reports_an_update_when_commits_differ(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['sha' => 'deadbeef00000000000000000000000000000000']),
        ]);

        $this->assertTrue(app(UpdateCheckerService::class)->isUpdateAvailable());
    }

    public function test_it_is_honest_when_github_is_unreachable(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(null, 500),
        ]);

        $this->assertFalse(app(UpdateCheckerService::class)->isUpdateAvailable());
    }
}
