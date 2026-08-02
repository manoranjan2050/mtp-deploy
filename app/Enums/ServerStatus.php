<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServerStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Connected = 'connected';
    case Unreachable = 'unreachable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Connected => 'Connected',
            self::Unreachable => 'Unreachable',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Connected => 'success',
            self::Unreachable => 'danger',
        };
    }
}
