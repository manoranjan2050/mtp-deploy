<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DatabaseStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Restoring = 'restoring';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Restoring => 'Restoring',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Restoring => 'warning',
        };
    }
}
