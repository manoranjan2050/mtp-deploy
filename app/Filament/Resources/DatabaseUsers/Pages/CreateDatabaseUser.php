<?php

declare(strict_types=1);

namespace App\Filament\Resources\DatabaseUsers\Pages;

use App\Actions\Databases\CreateDatabaseUserAction;
use App\DTOs\Databases\CreateDatabaseUserData;
use App\Filament\Resources\DatabaseUsers\DatabaseUserResource;
use App\Models\DatabaseUser;
use Filament\Resources\Pages\CreateRecord;

class CreateDatabaseUser extends CreateRecord
{
    protected static string $resource = DatabaseUserResource::class;

    protected function handleRecordCreation(array $data): DatabaseUser
    {
        return app(CreateDatabaseUserAction::class)->handle(new CreateDatabaseUserData(
            serverId: $data['server_id'],
            username: $data['username'],
            password: $data['password'],
            host: $data['host'] ?? '%',
            createdBy: auth()->id(),
        ));
    }
}
