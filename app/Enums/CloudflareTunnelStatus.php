<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CloudflareTunnelStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Error => 'Error',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Error => 'danger',
        };
    }
}
