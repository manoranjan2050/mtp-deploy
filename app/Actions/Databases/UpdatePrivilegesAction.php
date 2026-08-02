<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\Models\Database;
use App\Models\DatabaseUser;
use App\Services\Databases\DatabaseManagerService;

class UpdatePrivilegesAction
{
    public function __construct(
        private readonly DatabaseManagerService $manager,
    ) {}

    /**
     * @param  list<string>  $privileges  empty array revokes all access
     */
    public function handle(DatabaseUser $user, Database $database, array $privileges): void
    {
        if ($privileges === []) {
            $this->manager->revokeAllPrivileges($user->username, $user->host, $database->name);
            $user->databases()->detach($database);

            return;
        }

        $this->manager->grantPrivileges($user->username, $user->host, $database->name, $privileges);

        $user->databases()->syncWithoutDetaching([
            $database->id => ['privileges' => $privileges],
        ]);
    }
}
