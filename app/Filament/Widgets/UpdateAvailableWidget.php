<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Updates\UpdateCheckerService;
use Filament\Widgets\Widget;

class UpdateAvailableWidget extends Widget
{
    protected string $view = 'filament.widgets.update-available-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function isUpdateAvailable(): bool
    {
        return app(UpdateCheckerService::class)->isUpdateAvailable();
    }
}
