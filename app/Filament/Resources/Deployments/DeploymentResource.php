<?php

declare(strict_types=1);

namespace App\Filament\Resources\Deployments;

use App\Filament\Resources\Deployments\Pages\ListDeployments;
use App\Filament\Resources\Deployments\Pages\ViewDeployment;
use App\Filament\Resources\Deployments\Schemas\DeploymentInfolist;
use App\Filament\Resources\Deployments\Tables\DeploymentsTable;
use App\Models\Deployment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only by design - a deployment is only ever created by
 * TriggerDeploymentAction (manual deploy button or webhook), never hand-typed
 * through a form. Rollback lives as a row action in DeploymentsTable, not a
 * separate edit flow.
 */
class DeploymentResource extends Resource
{
    protected static ?string $model = Deployment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|\UnitEnum|null $navigationGroup = 'Websites';

    public static function infolist(Schema $schema): Schema
    {
        return DeploymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeploymentsTable::configure($table);
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeployments::route('/'),
            'view' => ViewDeployment::route('/{record}'),
        ];
    }
}
