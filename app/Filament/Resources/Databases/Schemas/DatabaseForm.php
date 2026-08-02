<?php

declare(strict_types=1);

namespace App\Filament\Resources\Databases\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DatabaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('server_id')
                    ->relationship('server', 'name')
                    ->required(),
                Select::make('website_id')
                    ->relationship('website', 'domain')
                    ->label('Website')
                    ->helperText('Optional - link this database to a website for developer-scoped access.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-zA-Z0-9_]+$/')
                    ->helperText('Letters, numbers, and underscores only.'),
                TextInput::make('charset')
                    ->required()
                    ->default('utf8mb4'),
                TextInput::make('collation')
                    ->required()
                    ->default('utf8mb4_unicode_ci'),
            ]);
    }
}
