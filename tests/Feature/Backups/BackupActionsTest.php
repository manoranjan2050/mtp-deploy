<?php

declare(strict_types=1);

namespace Tests\Feature\Backups;

use App\Actions\Backups\CreateBackupAction;
use App\Actions\Backups\DeleteBackupAction;
use App\Actions\Backups\RestoreBackupAction;
use App\Actions\Databases\CreateDatabaseAction;
use App\DTOs\Databases\CreateDatabaseData;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Exercises the full backup/restore cycle against real infrastructure - a
 * real zip archive of a real document root, a real mysqldump/restore round
 * trip against this dev machine's actual local MySQL (same pattern as
 * DatabaseActionsTest, Module 4), and real git commits (GitBackupServiceTest
 * already covers the git mechanics in isolation; this proves the Action
 * layer wires it up end to end).
 */
class BackupActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private string $backupsPath;

    private string $gitBackupsPath;

    private string $dbName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRoot = sys_get_temp_dir().'/mtp-backup-actions-docroot-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);
        File::put("{$this->documentRoot}/index.php", 'original content');

        $this->backupsPath = sys_get_temp_dir().'/mtp-backup-actions-storage-'.uniqid();
        config(['mtp.website_backups_path' => $this->backupsPath]);

        $this->gitBackupsPath = sys_get_temp_dir().'/mtp-backup-actions-git-'.uniqid();
        config(['mtp.git_backups_path' => $this->gitBackupsPath]);

        $this->dbName = 'mtp_backup_test_'.substr(md5(uniqid('', true)), 0, 10);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);
        File::deleteDirectory($this->backupsPath);
        File::deleteDirectory($this->gitBackupsPath);
        DB::connection('mysql_admin')->statement("DROP DATABASE IF EXISTS `{$this->dbName}`");

        parent::tearDown();
    }

    public function test_a_files_only_backup_can_be_created_and_restored(): void
    {
        $website = $this->website();

        $backup = app(CreateBackupAction::class)->handle($website, BackupType::Files);

        $this->assertSame(BackupStatus::Success, $backup->status);
        $this->assertFileExists($backup->disk_path);

        File::put("{$this->documentRoot}/index.php", 'corrupted!');

        app(RestoreBackupAction::class)->handle($backup);

        $this->assertSame('original content', File::get("{$this->documentRoot}/index.php"));
    }

    public function test_a_database_backup_bundles_a_real_mysqldump_and_restores_it(): void
    {
        $website = $this->website();
        $this->createRealDatabase($website);

        DB::connection('mysql_admin')->statement("USE `{$this->dbName}`");
        DB::connection('mysql_admin')->statement('CREATE TABLE widgets (id INT PRIMARY KEY, name VARCHAR(50))');
        DB::connection('mysql_admin')->statement("INSERT INTO widgets VALUES (1, 'gizmo')");

        $backup = app(CreateBackupAction::class)->handle($website, BackupType::Database);
        $this->assertSame(BackupStatus::Success, $backup->status);

        DB::connection('mysql_admin')->statement('DROP TABLE widgets');

        app(RestoreBackupAction::class)->handle($backup);

        $rows = DB::connection('mysql_admin')->select("SELECT name FROM `{$this->dbName}`.widgets WHERE id = 1");
        $this->assertSame('gizmo', $rows[0]->name);
    }

    public function test_a_full_backup_restores_both_files_and_database(): void
    {
        $website = $this->website();
        $this->createRealDatabase($website);

        DB::connection('mysql_admin')->statement("USE `{$this->dbName}`");
        DB::connection('mysql_admin')->statement('CREATE TABLE widgets (id INT PRIMARY KEY, name VARCHAR(50))');
        DB::connection('mysql_admin')->statement("INSERT INTO widgets VALUES (1, 'gizmo')");

        $backup = app(CreateBackupAction::class)->handle($website, BackupType::Full);
        $this->assertSame(BackupStatus::Success, $backup->status);

        File::put("{$this->documentRoot}/index.php", 'corrupted!');
        DB::connection('mysql_admin')->statement('DROP TABLE widgets');

        app(RestoreBackupAction::class)->handle($backup);

        $this->assertSame('original content', File::get("{$this->documentRoot}/index.php"));
        $rows = DB::connection('mysql_admin')->select("SELECT name FROM `{$this->dbName}`.widgets WHERE id = 1");
        $this->assertSame('gizmo', $rows[0]->name);
    }

    public function test_a_git_snapshot_backup_can_be_created_and_restored(): void
    {
        $website = $this->website();

        $backup = app(CreateBackupAction::class)->handle($website, BackupType::GitSnapshot);
        $this->assertSame(BackupStatus::Success, $backup->status);

        File::put("{$this->documentRoot}/index.php", 'corrupted!');

        app(RestoreBackupAction::class)->handle($backup);

        $this->assertSame('original content', File::get("{$this->documentRoot}/index.php"));
    }

    public function test_a_failed_backup_is_recorded_with_the_error(): void
    {
        $website = $this->website();
        // No database attached - a "database" backup has nothing to dump.

        try {
            app(CreateBackupAction::class)->handle($website, BackupType::Database);
            $this->fail('Expected an exception.');
        } catch (\RuntimeException) {
            // expected
        }

        $backup = $website->backups()->first();
        $this->assertSame(BackupStatus::Failed, $backup->status);
        $this->assertNotNull($backup->error);
    }

    public function test_deleting_a_files_backup_removes_the_archive(): void
    {
        $website = $this->website();
        $backup = app(CreateBackupAction::class)->handle($website, BackupType::Files);

        app(DeleteBackupAction::class)->handle($backup);

        $this->assertFileDoesNotExist($backup->disk_path);
        $this->assertDatabaseMissing('backups', ['id' => $backup->id]);
    }

    private function createRealDatabase(Website $website): void
    {
        app(CreateDatabaseAction::class)->handle(new CreateDatabaseData(
            serverId: $website->server_id,
            name: $this->dbName,
            websiteId: $website->id,
        ));
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
