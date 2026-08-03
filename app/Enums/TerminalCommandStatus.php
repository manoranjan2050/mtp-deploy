<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TerminalCommandStatus: string implements HasColor, HasLabel
{
    case Executed = 'executed';
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Executed => 'Executed',
            self::Blocked => 'Blocked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Executed => 'success',
            self::Blocked => 'danger',
        };
    }
}
