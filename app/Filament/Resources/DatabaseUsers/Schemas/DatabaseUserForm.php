<?php

declare(strict_types=1);

namespace App\Filament\Resources\DatabaseUsers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DatabaseUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('server_id')
                    ->relationship('server', 'name')
                    ->required(),
                TextInput::make('username')
                    ->required()
                    ->maxLength(32)
                    ->regex('/^[a-zA-Z0-9_]+$/')
                    ->helperText('Letters, numbers, and underscores only.'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255),
                TextInput::make('host')
                    ->required()
                    ->default('%')
                    ->helperText('Use % for any host, or a specific IP/hostname.'),
            ]);
    }
}
