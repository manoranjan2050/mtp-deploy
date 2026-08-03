<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlertMetric: string implements HasColor, HasLabel
{
    case Cpu = 'cpu';
    case Memory = 'memory';
    case Disk = 'disk';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cpu => 'CPU usage',
            self::Memory => 'Memory usage',
            self::Disk => 'Disk usage',
        };
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
