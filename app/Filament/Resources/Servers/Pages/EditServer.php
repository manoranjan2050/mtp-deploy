<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->is_local),
        ];
    }
}
