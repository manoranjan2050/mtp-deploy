<?php

declare(strict_types=1);

namespace App\Filament\Resources\Databases\Tables;

use App\Actions\Databases\BackupDatabaseAction;
use App\Actions\Databases\DeleteDatabaseAction;
use App\Actions\Databases\RestoreDatabaseAction;
use App\Actions\Databases\UpdatePrivilegesAction;
use App\Enums\DatabasePrivilege;
use App\Models\Database;
use App\Models\DatabaseUser;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('server.name')
                    ->label('Server'),
                TextColumn::make('website.domain')
                    ->label('Website')
                    ->placeholder('—'),
                TextColumn::make('charset'),
                TextColumn::make('status')
                    ->badge(),
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
                Action::make('backup')
                    ->label('Backup')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->authorize(fn (Database $record): bool => auth()->user()->can('view', $record))
                    ->action(function (Database $record): void {
                        $path = app(BackupDatabaseAction::class)->handle($record);

                        Notification::make()
                            ->title('Backup created')
                            ->body(basename($path))
                            ->success()
                            ->send();
                    }),
                Action::make('restore')
                    ->label('Restore')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->authorize(fn (Database $record): bool => auth()->user()->can('delete', $record))
                    ->requiresConfirmation()
                    ->modalDescription('This overwrites the current contents of the database with the uploaded backup.')
                    ->schema([
                        FileUpload::make('backup_file')
                            ->label('.sql backup file')
                            ->required()
                            ->acceptedFileTypes(['application/sql', 'text/plain', '.sql'])
                            ->disk('local')
                            ->directory('database-restore-uploads'),
                    ])
                    ->action(function (Database $record, array $data): void {
                        $uploadedPath = Storage::disk('local')->path($data['backup_file']);

                        try {
                            app(RestoreDatabaseAction::class)->handle($record, $uploadedPath);

                            Notification::make()->title('Database restored')->success()->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('Restore failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        } finally {
                            Storage::disk('local')->delete($data['backup_file']);
                        }
                    }),
                Action::make('managePrivileges')
                    ->label('Manage privileges')
                    ->icon(Heroicon::OutlinedKey)
                    ->authorize(fn (Database $record): bool => auth()->user()->can('managePrivileges', $record))
                    ->schema([
                        Select::make('database_user_id')
                            ->label('Database user')
                            ->options(fn (): array => DatabaseUser::query()->pluck('username', 'id')->all())
                            ->required(),
                        CheckboxList::make('privileges')
                            ->options(DatabasePrivilege::options())
                            ->helperText('Leave empty to revoke all access for this user.'),
                    ])
                    ->action(function (Database $record, array $data): void {
                        $user = DatabaseUser::query()->findOrFail($data['database_user_id']);

                        app(UpdatePrivilegesAction::class)->handle($user, $record, $data['privileges'] ?? []);

                        Notification::make()->title('Privileges updated')->success()->send();
                    }),
                DeleteAction::make()
                    ->using(function (Database $record): void {
                        app(DeleteDatabaseAction::class)->handle($record);
                    }),
            ])
            ->toolbarActions([]);
    }
}
