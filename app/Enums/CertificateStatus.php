<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CertificateStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Expiring => 'Expiring soon',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Active => 'success',
            self::Expiring => 'warning',
            self::Expired, self::Revoked, self::Failed => 'danger',
        };
    }
}
