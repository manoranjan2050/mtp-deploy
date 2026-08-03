<?php

declare(strict_types=1);

namespace App\Actions\Ssl;

use App\Enums\CertificateType;
use App\Models\SslCertificate;
use InvalidArgumentException;

class RenewCertificateAction
{
    public function __construct(
        private readonly IssueLetsEncryptCertificateAction $issue,
    ) {}

    public function handle(SslCertificate $certificate): SslCertificate
    {
        if ($certificate->type !== CertificateType::LetsEncrypt) {
            throw new InvalidArgumentException("Only Let's Encrypt certificates can be auto-renewed - upload a new custom certificate instead.");
        }

        return $this->issue->handle($certificate->website, $certificate->domains, $this->usesDnsChallenge($certificate));
    }

    /**
     * A wildcard domain can only ever have been issued via dns-01 (http-01
     * cannot validate a wildcard per the ACME spec), so its presence in the
     * stored domain list is what tells a renewal which challenge type to
     * repeat - the original request's `useDnsChallenge` flag itself isn't
     * persisted anywhere.
     */
    private function usesDnsChallenge(SslCertificate $certificate): bool
    {
        return collect($certificate->domains)->contains(fn (string $domain): bool => str_starts_with($domain, '*.'));
    }
}
