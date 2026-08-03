<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServer extends CreateRecord
{
    protected static string $resource = ServerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
