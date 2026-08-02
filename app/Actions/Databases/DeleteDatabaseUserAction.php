<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\Models\DatabaseUser;
use App\Services\Databases\DatabaseManagerService;

class DeleteDatabaseUserAction
{
    public function __construct(
        private readonly DatabaseManagerService $manager,
    ) {}

    public function handle(DatabaseUser $user): void
    {
        $this->manager->dropUser($user->username, $user->host);

        $user->delete();
    }
}
