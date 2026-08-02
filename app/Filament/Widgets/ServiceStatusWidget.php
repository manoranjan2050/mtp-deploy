<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ServiceStatus;
use App\Services\System\ServiceStatusService;
use Filament\Widgets\Widget;

class ServiceStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.service-status-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, ServiceStatus>
     */
    public function getServiceStatuses(): array
    {
        return app(ServiceStatusService::class)->checkAll();
    }

    public function getPhpVersion(): string
    {
        return app(ServiceStatusService::class)->phpVersion();
    }
}
