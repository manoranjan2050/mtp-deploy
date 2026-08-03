<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Databases;

use App\Services\Databases\DatabaseManagerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Exercises real CREATE/DROP DATABASE and CREATE/DROP USER / GRANT statements
 * against this dev machine's actual local MySQL (the `mysql_admin` connection,
 * root@127.0.0.1, no password - see phpunit.xml). Every test uses a uniquely
 * named throwaway database/user and cleans up in tearDown, so a failed
 * assertion never leaves stray state behind.
 */
class DatabaseManagerServiceTest extends TestCase
{
    private DatabaseManagerService $manager;

    private string $dbName;

    private string $username;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new DatabaseManagerService;
        $suffix = substr(md5(uniqid('', true)), 0, 10);
        $this->dbName = 'mtp_testdb_'.$suffix;
        $this->username = 'mtp_testuser_'.$suffix;
    }

    protected function tearDown(): void
    {
        DB::connection('mysql_admin')->statement("DROP DATABASE IF EXISTS `{$this->dbName}`");
        DB::connection('mysql_admin')->statement("DROP USER IF EXISTS `{$this->username}`@'%'");

        parent::tearDown();
    }

    public function test_it_creates_and_drops_a_real_database(): void
    {
        $this->manager->createDatabase($this->dbName, 'utf8mb4', 'utf8mb4_unicode_ci');

        $this->assertRealDatabaseExists($this->dbName);

        $this->manager->dropDatabase($this->dbName);

        $this->assertRealDatabaseMissing($this->dbName);
    }

    public function test_it_creates_and_drops_a_real_user(): void
    {
        $this->manager->createUser($this->username, 'a-real-password-123', '%');

        $this->assertRealUserExists($this->username);

        $this->manager->dropUser($this->username, '%');

        $this->assertRealUserMissing($this->username);
    }

    public function test_it_grants_and_revokes_real_privileges(): void
    {
        $this->manager->createDatabase($this->dbName, 'utf8mb4', 'utf8mb4_unicode_ci');
        $this->manager->createUser($this->username, 'a-real-password-123', '%');

        $this->manager->grantPrivileges($this->username, '%', $this->dbName, ['SELECT', 'INSERT']);

        $grants = collect(DB::connection('mysql_admin')->select("SHOW GRANTS FOR `{$this->username}`@'%'"))
            ->map(fn ($row): string => (array_values((array) $row))[0])
            ->implode(' | ');

        $this->assertStringContainsString('SELECT', $grants);
        $this->assertStringContainsString('INSERT', $grants);
        $this->assertStringContainsString($this->dbName, $grants);

        $this->manager->revokeAllPrivileges($this->username, '%', $this->dbName);

        $grantsAfterRevoke = collect(DB::connection('mysql_admin')->select("SHOW GRANTS FOR `{$this->username}`@'%'"))
            ->map(fn ($row): string => (array_values((array) $row))[0])
            ->implode(' | ');

        $this->assertStringNotContainsString($this->dbName, $grantsAfterRevoke);
    }

    public function test_it_rejects_creating_a_database_that_already_exists(): void
    {
        // Confirmed live: creating a database that already exists (e.g. one
        // provisioned outside the panel, or a double-click) previously threw
        // a raw QueryException all the way up to a blank Filament 500 error
        // page instead of a friendly validation message.
        $this->manager->createDatabase($this->dbName, 'utf8mb4', 'utf8mb4_unicode_ci');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A database named \"{$this->dbName}\" already exists");

        $this->manager->createDatabase($this->dbName, 'utf8mb4', 'utf8mb4_unicode_ci');
    }

    public function test_it_rejects_an_invalid_database_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->createDatabase('bad; DROP TABLE users; --', 'utf8mb4', 'utf8mb4_unicode_ci');
    }

    public function test_it_rejects_a_privilege_not_on_the_allowed_list(): void
    {
        $this->manager->createDatabase($this->dbName, 'utf8mb4', 'utf8mb4_unicode_ci');
        $this->manager->createUser($this->username, 'a-real-password-123', '%');

        $this->expectException(InvalidArgumentException::class);

        $this->manager->grantPrivileges($this->username, '%', $this->dbName, ['SUPER']);
    }

    private function assertRealDatabaseExists(string $name): void
    {
        $result = DB::connection('mysql_admin')->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name]
        );

        $this->assertNotEmpty($result, "Expected database \"{$name}\" to exist.");
    }

    private function assertRealDatabaseMissing(string $name): void
    {
        $result = DB::connection('mysql_admin')->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$name]
        );

        $this->assertEmpty($result, "Expected database \"{$name}\" to not exist.");
    }

    private function assertRealUserExists(string $username): void
    {
        $result = DB::connection('mysql_admin')->select(
            'SELECT User FROM mysql.user WHERE User = ?',
            [$username]
        );

        $this->assertNotEmpty($result, "Expected user \"{$username}\" to exist.");
    }

    private function assertRealUserMissing(string $username): void
    {
        $result = DB::connection('mysql_admin')->select(
            'SELECT User FROM mysql.user WHERE User = ?',
            [$username]
        );

        $this->assertEmpty($result, "Expected user \"{$username}\" to not exist.");
    }
}
