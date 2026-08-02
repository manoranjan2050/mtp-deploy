<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Tables;

use App\Actions\Deployments\TriggerDeploymentAction;
use App\Actions\Websites\ChangePhpVersionAction;
use App\Actions\Websites\CloneWebsiteAction;
use App\Actions\Websites\RestartNginxAction;
use App\Actions\Websites\RestartPhpAction;
use App\Actions\Websites\SuspendWebsiteAction;
use App\Actions\Websites\ToggleSslAction;
use App\Enums\DeploymentTrigger;
use App\Enums\SslStatus;
use App\Enums\WebsiteStatus;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\Website;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WebsitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable(),
                TextColumn::make('php_version')
                    ->label('PHP'),
                TextColumn::make('framework')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('ssl_status')
                    ->label('SSL')
                    ->badge(),
                TextColumn::make('creator.name')
                    ->label('Created by')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->label(fn (Website $record): string => $record->status === WebsiteStatus::Active ? 'Suspend' : 'Reinstate')
                    ->icon(fn (Website $record): Heroicon => $record->status === WebsiteStatus::Active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (Website $record): string => $record->status === WebsiteStatus::Active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->authorize(fn (Website $record): bool => auth()->user()->can('suspend', $record))
                    ->action(function (Website $record): void {
                        $result = app(SuspendWebsiteAction::class)->handle($record);

                        Notification::make()
                            ->title($record->fresh()->status === WebsiteStatus::Suspended ? 'Website suspended' : 'Website reinstated')
                            ->success($result->successful)
                            ->warning(! $result->successful)
                            ->send();
                    }),
                Action::make('clone')
                    ->label('Clone')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->authorize(fn (Website $record): bool => auth()->user()->can('create', Website::class))
                    ->schema([
                        TextInput::make('domain')
                            ->label('New domain')
                            ->required()
                            ->unique('websites', 'domain'),
                    ])
                    ->action(function (Website $record, array $data): void {
                        app(CloneWebsiteAction::class)->handle($record, $data['domain'], auth()->id());

                        Notification::make()->title('Website cloned')->success()->send();
                    }),
                Action::make('togglePhpVersion')
                    ->label('Change PHP version')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->authorize(fn (Website $record): bool => auth()->user()->can('update', $record))
                    ->schema(fn (Website $record): array => [
                        Select::make('php_version')
                            ->label('PHP version')
                            ->options(collect($record->server->availablePhpVersions())->mapWithKeys(fn (string $v): array => [$v => $v]))
                            ->default($record->php_version)
                            ->required(),
                    ])
                    ->action(function (Website $record, array $data): void {
                        $result = app(ChangePhpVersionAction::class)->handle($record, $data['php_version']);

                        Notification::make()
                            ->title('PHP version updated')
                            ->success($result->successful)
                            ->warning(! $result->successful)
                            ->send();
                    }),
                Action::make('toggleSsl')
                    ->label(fn (Website $record): string => $record->ssl_status === SslStatus::None ? 'Enable SSL' : 'Disable SSL')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->authorize(fn (Website $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(function (Website $record): void {
                        $action = app(ToggleSslAction::class);

                        $record->ssl_status === SslStatus::None
                            ? $action->enable($record)
                            : $action->disable($record);

                        Notification::make()->title('SSL status updated')->success()->send();
                    }),
                Action::make('deploy')
                    ->label('Deploy')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->color('primary')
                    ->visible(fn (Website $record): bool => filled($record->repository_url))
                    ->authorize(fn (Website $record): bool => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Website $record): string => "Deploys the latest commit on \"{$record->git_branch}\".")
                    ->action(function (Website $record): void {
                        $deployment = app(TriggerDeploymentAction::class)->handle($record, DeploymentTrigger::Manual, auth()->user());

                        Notification::make()
                            ->title($deployment->status->getLabel())
                            ->success($deployment->status->value === 'success')
                            ->danger($deployment->status->value === 'failed')
                            ->send();
                    }),
                Action::make('restartPhp')
                    ->label('Restart PHP')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->authorize(fn (Website $record): bool => auth()->user()->can('manageServices', $record))
                    ->requiresConfirmation()
                    ->action(function (Website $record): void {
                        $result = app(RestartPhpAction::class)->handle($record);

                        Notification::make()
                            ->title($result->successful ? 'PHP-FPM restarted' : 'PHP-FPM restart failed')
                            ->body($result->successful ? null : $result->errorOutput)
                            ->success($result->successful)
                            ->danger(! $result->successful)
                            ->send();
                    }),
                Action::make('files')
                    ->label('Files')
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->authorize(fn (Website $record): bool => auth()->user()->can('manageFiles', $record))
                    ->url(fn (Website $record): string => WebsiteResource::getUrl('files', ['record' => $record])),
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('restartNginx')
                    ->label('Restart nginx')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->authorize(fn (): bool => auth()->user()->can('manage website services'))
                    ->requiresConfirmation()
                    ->modalDescription('This reloads nginx for every website on this server.')
                    ->action(function (): void {
                        $result = app(RestartNginxAction::class)->handle();

                        Notification::make()
                            ->title($result->successful ? 'nginx reloaded' : 'nginx reload failed')
                            ->body($result->successful ? null : $result->errorOutput)
                            ->success($result->successful)
                            ->danger(! $result->successful)
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
