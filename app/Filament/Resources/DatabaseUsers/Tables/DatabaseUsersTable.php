<?php

declare(strict_types=1);

namespace App\Filament\Resources\DatabaseUsers\Tables;

use App\Actions\Databases\DeleteDatabaseUserAction;
use App\Models\DatabaseUser;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DatabaseUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('server.name')
                    ->label('Server'),
                TextColumn::make('host'),
                TextColumn::make('databases.name')
                    ->label('Databases')
                    ->badge()
                    ->separator(','),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->using(function (DatabaseUser $record): void {
                        app(DeleteDatabaseUserAction::class)->handle($record);
                    }),
            ])
            ->toolbarActions([]);
    }
}
