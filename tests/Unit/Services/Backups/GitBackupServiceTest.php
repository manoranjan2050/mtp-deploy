<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Backups;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use App\Services\Backups\GitBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Real `git` commands against a real bare shadow repository - same
 * "real infrastructure over mocks" approach as GitDeploymentServiceTest
 * (Module 5), since git is genuinely installed on this machine.
 */
class GitBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private string $gitBackupsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRoot = sys_get_temp_dir().'/mtp-gitbackup-docroot-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);

        $this->gitBackupsPath = sys_get_temp_dir().'/mtp-gitbackup-repos-'.uniqid();
        config(['mtp.git_backups_path' => $this->gitBackupsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);
        File::deleteDirectory($this->gitBackupsPath);

        parent::tearDown();
    }

    public function test_it_snapshots_and_restores_a_real_document_root(): void
    {
        File::put("{$this->documentRoot}/index.php", 'version 1');
        $website = $this->website();
        $service = app(GitBackupService::class);

        $firstSha = $service->snapshot($website);
        $this->assertNotEmpty($firstSha);

        File::put("{$this->documentRoot}/index.php", 'version 2');
        $secondSha = $service->snapshot($website);

        $this->assertNotSame($firstSha, $secondSha);
        $this->assertSame('version 2', File::get("{$this->documentRoot}/index.php"));

        $service->restore($website, $firstSha);

        $this->assertSame('version 1', File::get("{$this->documentRoot}/index.php"));
    }

    public function test_history_returns_snapshots_newest_first(): void
    {
        File::put("{$this->documentRoot}/index.php", 'v1');
        $website = $this->website();
        $service = app(GitBackupService::class);

        $service->snapshot($website);
        File::put("{$this->documentRoot}/index.php", 'v2');
        $secondSha = $service->snapshot($website);

        $history = $service->history($website);

        $this->assertCount(2, $history);
        $this->assertSame($secondSha, $history[0]['sha']);
    }

    public function test_history_is_empty_when_no_snapshot_has_been_taken(): void
    {
        $website = $this->website();

        $this->assertSame([], app(GitBackupService::class)->history($website));
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::PlainPhp,
        ]);
    }
}
