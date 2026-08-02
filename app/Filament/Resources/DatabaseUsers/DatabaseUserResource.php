<?php

declare(strict_types=1);

namespace App\Filament\Resources\DatabaseUsers;

use App\Filament\Resources\DatabaseUsers\Pages\CreateDatabaseUser;
use App\Filament\Resources\DatabaseUsers\Pages\ListDatabaseUsers;
use App\Filament\Resources\DatabaseUsers\Schemas\DatabaseUserForm;
use App\Filament\Resources\DatabaseUsers\Tables\DatabaseUsersTable;
use App\Models\DatabaseUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * No Edit page - a MySQL user's password can't be safely round-tripped
 * through a form (never displayed once set), and username/host aren't
 * meaningfully alterable in place; delete and recreate instead.
 */
class DatabaseUserResource extends Resource
{
    protected static ?string $model = DatabaseUser::class;

    protected static ?string $recordTitleAttribute = 'username';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Websites';

    public static function form(Schema $schema): Schema
    {
        return DatabaseUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabaseUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatabaseUsers::route('/'),
            'create' => CreateDatabaseUser::route('/create'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
