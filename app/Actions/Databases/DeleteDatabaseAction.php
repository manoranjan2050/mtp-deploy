<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\Models\Database;
use App\Services\Databases\DatabaseManagerService;

class DeleteDatabaseAction
{
    public function __construct(
        private readonly DatabaseManagerService $manager,
    ) {}

    public function handle(Database $database): void
    {
        $this->manager->dropDatabase($database->name);

        $database->delete();
    }
}
