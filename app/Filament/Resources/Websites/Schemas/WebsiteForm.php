<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Schemas;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                Section::make('Deployment')
                    ->visibleOn('edit')
                    ->components([
                        TextInput::make('repository_url')
                            ->label('Repository URL')
                            ->helperText('SSH or HTTPS git URL, e.g. git@github.com:user/repo.git'),
                        TextInput::make('git_branch')
                            ->label('Branch')
                            ->default('main')
                            ->required(),
                        Toggle::make('auto_deploy')
                            ->label('Auto-deploy on push')
                            ->helperText('When enabled, the webhook URL below triggers a deployment automatically.'),
                        TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Website $record): ?string => $record
                                ? route('webhooks.deploy', $record->webhook_token)
                                : null)
                            ->helperText('Configure this as the webhook URL in your GitHub/GitLab/Bitbucket repo settings.'),
                    ]),
            ]);
    }
}
