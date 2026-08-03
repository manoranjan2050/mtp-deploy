<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QueueWorkerStatus: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Stopped => 'Stopped',
            self::Failed => 'Failed',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Stopped => 'gray',
            self::Failed => 'danger',
            self::Unknown => 'warning',
        };
    }
}
