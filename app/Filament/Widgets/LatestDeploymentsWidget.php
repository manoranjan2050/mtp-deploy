<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Placeholder until Module 5 (Deployment) ships a real deployments table.
 * Shows an honest empty state rather than fabricated data - see
 * docs/Vision.md's "never lie about server state" principle.
 */
class LatestDeploymentsWidget extends Widget
{
    protected string $view = 'filament.widgets.latest-deployments-widget';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';
}
