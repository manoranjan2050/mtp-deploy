<?php

namespace App\Filament\Resources\DatabaseUsers\Pages;

use App\Filament\Resources\DatabaseUsers\DatabaseUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseUsers extends ListRecords
{
    protected static string $resource = DatabaseUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
