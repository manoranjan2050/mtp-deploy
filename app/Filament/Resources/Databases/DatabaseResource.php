<?php

declare(strict_types=1);

namespace App\Filament\Resources\Databases;

use App\Filament\Resources\Databases\Pages\CreateDatabase;
use App\Filament\Resources\Databases\Pages\ListDatabases;
use App\Filament\Resources\Databases\Schemas\DatabaseForm;
use App\Filament\Resources\Databases\Tables\DatabasesTable;
use App\Models\Database;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * No Edit page - a database's name/charset/collation aren't meaningfully
 * editable after creation (renaming a real MySQL database isn't a single
 * statement); management happens via the table's row actions (backup,
 * restore, manage privileges, delete) instead.
 */
class DatabaseResource extends Resource
{
    protected static ?string $model = Database::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Websites';

    public static function form(Schema $schema): Schema
    {
        return DatabaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabasesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user?->hasRole('developer')) {
            $query->whereHas('website', fn (Builder $q) => $q->where('created_by', $user->id));
        }

        return $query;
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
            'index' => ListDatabases::route('/'),
            'create' => CreateDatabase::route('/create'),
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
