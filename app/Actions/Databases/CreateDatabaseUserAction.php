<?php

declare(strict_types=1);

namespace App\Actions\Databases;

use App\DTOs\Databases\CreateDatabaseUserData;
use App\Models\DatabaseUser;
use App\Services\Databases\DatabaseManagerService;

class CreateDatabaseUserAction
{
    public function __construct(
        private readonly DatabaseManagerService $manager,
    ) {}

    public function handle(CreateDatabaseUserData $data): DatabaseUser
    {
        $this->manager->createUser($data->username, $data->password, $data->host);

        return DatabaseUser::query()->create([
            'server_id' => $data->serverId,
            'username' => $data->username,
            'password' => $data->password,
            'host' => $data->host,
            'created_by' => $data->createdBy,
        ]);
    }
}
