<?php

declare(strict_types=1);

namespace App\DTOs\Ssl;

use Illuminate\Support\Carbon;

final readonly class CertificateDetails
{
    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public array $domains,
        public Carbon $issuedAt,
        public Carbon $expiresAt,
    ) {}
}
