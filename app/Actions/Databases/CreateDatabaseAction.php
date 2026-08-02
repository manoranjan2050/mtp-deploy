<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\DTOs\Databases\CreateDatabaseData;
use App\Models\Database;
use App\Services\Databases\DatabaseManagerService;

/**
 * Unlike CreateWebsiteAction, this only creates the metadata row if the real
 * `CREATE DATABASE` succeeds - a "database" record with no database behind it
 * would be actively misleading (websites can meaningfully exist before nginx
 * catches up; a database record cannot).
 */
class CreateDatabaseAction
{
    public function __construct(
        private readonly DatabaseManagerService $manager,
    ) {}

    public function handle(CreateDatabaseData $data): Database
    {
        $this->manager->createDatabase($data->name, $data->charset, $data->collation);

        return Database::query()->create([
            'server_id' => $data->serverId,
            'website_id' => $data->websiteId,
            'name' => $data->name,
            'charset' => $data->charset,
            'collation' => $data->collation,
            'created_by' => $data->createdBy,
        ]);
    }
}
