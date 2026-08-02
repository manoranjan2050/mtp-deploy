<?php

declare(strict_types=1);

namespace Tests\Feature\Databases;

use App\Actions\Databases\CreateDatabaseAction;
use App\Actions\Databases\CreateDatabaseUserAction;
use App\Actions\Databases\DeleteDatabaseAction;
use App\Actions\Databases\DeleteDatabaseUserAction;
use App\Actions\Databases\UpdatePrivilegesAction;
use App\DTOs\Databases\CreateDatabaseData;
use App\DTOs\Databases\CreateDatabaseUserData;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseActionsTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private string $dbName;

    private string $username;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        $this->dbName = 'mtp_testdb_'.substr(md5(uniqid('', true)), 0, 10);
        $this->username = 'mtp_testuser_'.substr(md5(uniqid('', true)), 0, 10);
    }

    protected function tearDown(): void
    {
        DB::connection('mysql_admin')->statement("DROP DATABASE IF EXISTS `{$this->dbName}`");
        DB::connection('mysql_admin')->statement("DROP USER IF EXISTS `{$this->username}`@'%'");

        parent::tearDown();
    }

    public function test_create_database_action_creates_a_real_database_and_a_record(): void
    {
        $database = app(CreateDatabaseAction::class)->handle(new CreateDatabaseData(
            serverId: $this->server->id,
            name: $this->dbName,
        ));

        $this->assertDatabaseHas('databases', ['name' => $this->dbName]);

        $exists = DB::connection('mysql_admin')->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$this->dbName]
        );
        $this->assertNotEmpty($exists);
        $this->assertSame($this->dbName, $database->name);
    }

    public function test_delete_database_action_drops_the_real_database_and_soft_deletes(): void
    {
        $database = app(CreateDatabaseAction::class)->handle(new CreateDatabaseData(
            serverId: $this->server->id,
            name: $this->dbName,
        ));

        app(DeleteDatabaseAction::class)->handle($database);

        $this->assertSoftDeleted('databases', ['id' => $database->id]);

        $exists = DB::connection('mysql_admin')->select(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$this->dbName]
        );
        $this->assertEmpty($exists);
    }

    public function test_create_and_delete_database_user_action(): void
    {
        $user = app(CreateDatabaseUserAction::class)->handle(new CreateDatabaseUserData(
            serverId: $this->server->id,
            username: $this->username,
            password: 'a-real-password-123',
        ));

        $this->assertDatabaseHas('database_users', ['username' => $this->username]);

        app(DeleteDatabaseUserAction::class)->handle($user);

        $this->assertSoftDeleted('database_users', ['id' => $user->id]);

        $exists = DB::connection('mysql_admin')->select('SELECT User FROM mysql.user WHERE User = ?', [$this->username]);
        $this->assertEmpty($exists);
    }

    public function test_update_privileges_action_grants_then_revokes(): void
    {
        $database = app(CreateDatabaseAction::class)->handle(new CreateDatabaseData(
            serverId: $this->server->id,
            name: $this->dbName,
        ));
        $user = app(CreateDatabaseUserAction::class)->handle(new CreateDatabaseUserData(
            serverId: $this->server->id,
            username: $this->username,
            password: 'a-real-password-123',
        ));

        app(UpdatePrivilegesAction::class)->handle($user, $database, ['SELECT', 'INSERT']);

        $this->assertDatabaseHas('database_user_database', [
            'database_user_id' => $user->id,
            'database_id' => $database->id,
        ]);

        app(UpdatePrivilegesAction::class)->handle($user, $database, []);

        $this->assertDatabaseMissing('database_user_database', [
            'database_user_id' => $user->id,
            'database_id' => $database->id,
        ]);
    }
}
