<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WebsiteFramework: string implements HasLabel
{
    case Laravel = 'laravel';
    case PlainPhp = 'plain-php';
    case Static = 'static';

    public function getLabel(): string
    {
        return match ($this) {
            self::Laravel => 'Laravel',
            self::PlainPhp => 'Plain PHP',
            self::Static => 'Static HTML',
        };
    }

    /**
     * Relative to the site root - Laravel serves from `public/`, the others
     * serve from the root itself.
     */
    public function documentRootSuffix(): string
    {
        return match ($this) {
            self::Laravel => '/public',
            self::PlainPhp, self::Static => '',
        };
    }
}
