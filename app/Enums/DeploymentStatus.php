<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeploymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::RolledBack => 'Rolled back',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
            self::RolledBack => 'warning',
        };
    }
}
