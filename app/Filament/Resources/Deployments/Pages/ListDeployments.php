<?php

declare(strict_types=1);

namespace App\Filament\Resources\Deployments\Pages;

use App\Filament\Resources\Deployments\DeploymentResource;
use Filament\Resources\Pages\ListRecords;

class ListDeployments extends ListRecords
{
    protected static string $resource = DeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
