<?php

declare(strict_types=1);

namespace App\Filament\Resources\Databases\Pages;

use App\Actions\Databases\CreateDatabaseAction;
use App\DTOs\Databases\CreateDatabaseData;
use App\Filament\Resources\Databases\DatabaseResource;
use App\Models\Database;
use Filament\Resources\Pages\CreateRecord;

class CreateDatabase extends CreateRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function handleRecordCreation(array $data): Database
    {
        return app(CreateDatabaseAction::class)->handle(new CreateDatabaseData(
            serverId: $data['server_id'],
            name: $data['name'],
            websiteId: $data['website_id'] ?? null,
            charset: $data['charset'] ?? 'utf8mb4',
            collation: $data['collation'] ?? 'utf8mb4_unicode_ci',
            createdBy: auth()->id(),
        ));
    }
}
