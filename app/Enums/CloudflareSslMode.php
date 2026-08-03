<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CloudflareSslMode: string implements HasColor, HasLabel
{
    case Off = 'off';
    case Flexible = 'flexible';
    case Full = 'full';
    case FullStrict = 'strict';

    public function getLabel(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Flexible => 'Flexible',
            self::Full => 'Full',
            self::FullStrict => 'Full (Strict)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Off => 'danger',
            self::Flexible => 'warning',
            self::Full => 'success',
            self::FullStrict => 'success',
        };
    }
}
