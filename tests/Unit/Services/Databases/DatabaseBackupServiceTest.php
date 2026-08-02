<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Databases;

use App\Models\Database;
use App\Models\Server;
use App\Services\Databases\DatabaseBackupService;
use App\Services\Databases\DatabaseManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Exercises a real mysqldump/mysql-client round trip against this dev
 * machine's actual local MySQL - creates a throwaway database with one row of
 * data, backs it up, drops the row, restores from the backup, and asserts the
 * row is back. Not mocked; `mysqldump.exe`/`mysql.exe` genuinely run here via
 * AMPPS (see .env's MYSQLDUMP_PATH/MYSQL_CLI_PATH).
 */
class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $dbName;

    private string $tempBackupsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbName = 'mtp_testdb_'.substr(md5(uniqid('', true)), 0, 10);
        $this->tempBackupsPath = sys_get_temp_dir().'/mtp-deploy-backups-'.uniqid();

        config(['mtp.database_backups_path' => $this->tempBackupsPath]);

        (new DatabaseManagerService)->createDatabase($this->dbName, 'utf8mb4', 'utf8mb4_unicode_ci');

        DB::connection('mysql_admin')->statement("USE `{$this->dbName}`");
        DB::connection('mysql_admin')->statement('CREATE TABLE widgets (id INT PRIMARY KEY, name VARCHAR(100))');
        DB::connection('mysql_admin')->statement("INSERT INTO widgets VALUES (1, 'original-row')");
    }

    protected function tearDown(): void
    {
        DB::connection('mysql_admin')->statement("DROP DATABASE IF EXISTS `{$this->dbName}`");
        File::deleteDirectory($this->tempBackupsPath);

        parent::tearDown();
    }

    public function test_backup_and_restore_round_trip_preserves_data(): void
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $database = Database::query()->make([
            'server_id' => $server->id,
            'name' => $this->dbName,
        ]);

        $backupService = new DatabaseBackupService;
        $backupPath = $backupService->backup($database);

        $this->assertFileExists($backupPath);
        $this->assertStringContainsString('widgets', File::get($backupPath));

        // Simulate data loss, then restore.
        DB::connection('mysql_admin')->statement("DELETE FROM `{$this->dbName}`.widgets WHERE id = 1");

        $rowsAfterDelete = DB::connection('mysql_admin')->select("SELECT * FROM `{$this->dbName}`.widgets");
        $this->assertCount(0, $rowsAfterDelete);

        $backupService->restore($database, $backupPath);

        $rowsAfterRestore = DB::connection('mysql_admin')->select("SELECT * FROM `{$this->dbName}`.widgets");
        $this->assertCount(1, $rowsAfterRestore);
        $this->assertSame('original-row', $rowsAfterRestore[0]->name);
    }
}
