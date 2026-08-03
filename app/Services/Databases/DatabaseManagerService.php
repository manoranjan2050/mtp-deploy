<?php

declare(strict_types=1);

namespace App\Services\Databases;

use App\Enums\DatabasePrivilege;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Runs real CREATE/DROP DATABASE and CREATE/DROP USER statements against the
 * `mysql_admin` connection (config/database.php) - a separate, more-privileged
 * connection than the app's own `mysql` one, which only has grants on its own
 * database. See docs/Database.md and CLAUDE.md.
 *
 * MySQL identifiers (database/user names) can't be bound as parameters in DDL
 * - PDO only parameterizes values, not identifiers - so every identifier here
 * is strictly validated against an allowlist pattern before being
 * interpolated into SQL, never taken from user input unchecked.
 *
 * The `host` part of `user@host` is *also* interpolated rather than bound: a
 * `?` placeholder immediately after `@` trips up the MySQL PDO driver's
 * tokenizer (it reads `@?` as a user-defined-variable reference, not "at
 * symbol then a bindable placeholder"), producing a syntax error with the
 * placeholder left completely unsubstituted. Since `host` only ever needs to
 * be `%`, `localhost`, an IPv4 address, or a simple hostname, it's validated
 * against a strict allowlist pattern and safely quoted inline instead -
 * still never raw user input. The password *is* bound normally via
 * `IDENTIFIED BY ?`, which is an ordinary value position with no such issue.
 */
class DatabaseManagerService
{
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z0-9_]{1,64}$/';

    private const HOST_PATTERN = '/^[a-zA-Z0-9.%_-]{1,60}$/';

    public function createDatabase(string $name, string $charset, string $collation): void
    {
        $this->assertValidIdentifier($name);

        if ($this->databaseExists($name)) {
            throw new InvalidArgumentException("A database named \"{$name}\" already exists on the server.");
        }

        DB::connection('mysql_admin')->statement(
            sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s', $name, $charset, $collation)
        );
    }

    public function databaseExists(string $name): bool
    {
        $this->assertValidIdentifier($name);

        return DB::connection('mysql_admin')
            ->table('information_schema.schemata')
            ->where('schema_name', $name)
            ->exists();
    }

    public function dropDatabase(string $name): void
    {
        $this->assertValidIdentifier($name);

        DB::connection('mysql_admin')->statement(sprintf('DROP DATABASE IF EXISTS `%s`', $name));
    }

    public function createUser(string $username, string $plainTextPassword, string $host): void
    {
        $this->assertValidIdentifier($username);
        $this->assertValidHost($host);

        // MySQL's PDO driver does not support bound parameters in
        // CREATE USER ... IDENTIFIED BY - it isn't a preparable DML
        // statement, and a `?` placeholder there is left as a literal
        // question mark, causing a syntax error (confirmed against this
        // dev machine's real MySQL 8.0.46). PDO::quote() gives
        // driver-native escaping for safe manual interpolation instead.
        $quotedPassword = DB::connection('mysql_admin')->getPdo()->quote($plainTextPassword);

        DB::connection('mysql_admin')->statement(
            sprintf("CREATE USER `%s`@'%s' IDENTIFIED BY %s", $username, $host, $quotedPassword)
        );
    }

    public function dropUser(string $username, string $host): void
    {
        $this->assertValidIdentifier($username);
        $this->assertValidHost($host);

        DB::connection('mysql_admin')->statement(sprintf("DROP USER IF EXISTS `%s`@'%s'", $username, $host));
    }

    /**
     * @param  list<string>  $privileges  e.g. ['SELECT', 'INSERT'] or ['ALL PRIVILEGES']
     */
    public function grantPrivileges(string $username, string $host, string $databaseName, array $privileges): void
    {
        $this->assertValidIdentifier($username);
        $this->assertValidIdentifier($databaseName);
        $this->assertValidHost($host);

        $privilegeList = $this->assertValidPrivilegeList($privileges);

        DB::connection('mysql_admin')->statement(
            sprintf("GRANT %s ON `%s`.* TO `%s`@'%s'", $privilegeList, $databaseName, $username, $host)
        );

        DB::connection('mysql_admin')->statement('FLUSH PRIVILEGES');
    }

    public function revokeAllPrivileges(string $username, string $host, string $databaseName): void
    {
        $this->assertValidIdentifier($username);
        $this->assertValidIdentifier($databaseName);
        $this->assertValidHost($host);

        DB::connection('mysql_admin')->statement(
            sprintf("REVOKE ALL PRIVILEGES ON `%s`.* FROM `%s`@'%s'", $databaseName, $username, $host)
        );

        DB::connection('mysql_admin')->statement('FLUSH PRIVILEGES');
    }

    private function assertValidIdentifier(string $identifier): void
    {
        if (! preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new InvalidArgumentException(
                "Invalid identifier \"{$identifier}\" - only letters, numbers, and underscores are allowed (max 64 chars)."
            );
        }
    }

    private function assertValidHost(string $host): void
    {
        if (! preg_match(self::HOST_PATTERN, $host)) {
            throw new InvalidArgumentException(
                "Invalid host \"{$host}\" - only letters, numbers, dots, hyphens, underscores, and % are allowed (max 60 chars)."
            );
        }
    }

    /**
     * @param  list<string>  $privileges
     */
    private function assertValidPrivilegeList(array $privileges): string
    {
        $allowed = array_column(DatabasePrivilege::cases(), 'value');

        foreach ($privileges as $privilege) {
            if (! in_array($privilege, $allowed, true)) {
                throw new InvalidArgumentException("Privilege \"{$privilege}\" is not in the allowed list.");
            }
        }

        return Str::of(implode(', ', $privileges))->trim()->toString();
    }
}
