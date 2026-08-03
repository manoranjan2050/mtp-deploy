<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CertificateType: string implements HasColor, HasLabel
{
    case LetsEncrypt = 'lets_encrypt';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::LetsEncrypt => "Let's Encrypt",
            self::Custom => 'Custom',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LetsEncrypt => 'success',
            self::Custom => 'gray',
        };
    }
}
