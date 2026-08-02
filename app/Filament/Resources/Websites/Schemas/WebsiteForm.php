<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Schemas;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WebsiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('server_id')
                    ->relationship('server', 'name')
                    ->required()
                    ->live()
                    ->disabled(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Changing the domain requires deleting and recreating the website (Module 3 does not yet support in-place domain changes).'
                        : null),
                TagsInput::make('aliases')
                    ->placeholder('www.example.com'),
                Select::make('php_version')
                    ->required()
                    ->options(function (Schema $schema, ?int $serverId, ?Website $record): array {
                        $server = $serverId ? Server::find($serverId) : $record?->server;

                        return collect($server?->availablePhpVersions() ?? ['8.2', '8.3', '8.4'])
                            ->mapWithKeys(fn (string $version): array => [$version => $version])
                            ->all();
                    })
                    ->live(),
                Select::make('framework')
                    ->options(WebsiteFramework::class)
                    ->default(WebsiteFramework::Laravel)
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Changing the framework requires deleting and recreating the website.'
                        : null),
                TextInput::make('document_root')
                    ->label('Document root')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->helperText('Set automatically from the domain when the website is created.'),
            ]);
    }
}
