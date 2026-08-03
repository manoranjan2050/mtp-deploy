<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Tables;

use App\Actions\Servers\TestServerConnectionAction;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('ssh_host')
                    ->label('SSH host')
                    ->searchable(),
                IconColumn::make('is_local')
                    ->label('Local')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('tags')
                    ->badge()
                    ->separator(','),
                TextColumn::make('last_connected_at')
                    ->label('Last connected')
                    ->dateTime()
                    ->placeholder('Never'),
            ])
            ->recordActions([
                Action::make('testConnection')
                    ->label('Test connection')
                    ->icon(Heroicon::OutlinedSignal)
                    ->visible(fn (Server $record): bool => ! $record->is_local)
                    ->authorize(fn (Server $record): bool => auth()->user()->can('testConnection', $record))
                    ->action(function (Server $record): void {
                        $result = app(TestServerConnectionAction::class)->handle($record);

                        Notification::make()
                            ->title($result->successful ? 'Connected' : 'Connection failed')
                            ->body($result->successful ? $result->output : $result->errorOutput)
                            ->success($result->successful)
                            ->danger(! $result->successful)
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Server $record): bool => ! $record->is_local),
            ]);
    }
}
