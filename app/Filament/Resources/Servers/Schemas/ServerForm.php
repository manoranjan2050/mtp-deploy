<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('hostname')
                    ->maxLength(255)
                    ->helperText('A friendly hostname/label, e.g. "prod-web-1.example.com" - not used for the SSH connection itself.'),
                TextInput::make('ssh_host')
                    ->label('SSH host')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ssh_port')
                    ->label('SSH port')
                    ->numeric()
                    ->default(22)
                    ->required(),
                TextInput::make('ssh_user')
                    ->label('SSH user')
                    ->required()
                    ->maxLength(255),
                Textarea::make('ssh_private_key')
                    ->label('SSH private key')
                    ->rows(8)
                    ->helperText('A private key this app will use to connect - stored encrypted. Never the server\'s only copy of this key.')
                    ->visibleOn('create')
                    ->required(fn (string $operation): bool => $operation === 'create'),
                TagsInput::make('tags')
                    ->placeholder('production, us-east')
                    ->helperText('Freeform tags for grouping/filtering servers.'),
            ]);
    }
}
