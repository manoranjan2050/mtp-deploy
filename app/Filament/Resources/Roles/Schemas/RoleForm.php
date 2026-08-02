<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                CheckboxList::make('permissions')
                    ->relationship('permissions', 'name')
                    ->options(fn (): array => Permission::query()->pluck('name', 'name')->all())
                    ->columns(2)
                    ->bulkToggleable(),
            ]);
    }
}
