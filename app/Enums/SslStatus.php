<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SslStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'No SSL',
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Pending => 'warning',
            self::Active => 'success',
            self::Expired => 'danger',
        };
    }
}
