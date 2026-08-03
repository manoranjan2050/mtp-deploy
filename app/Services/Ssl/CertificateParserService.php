<?php

declare(strict_types=1);

namespace App\Services\Ssl;

use App\DTOs\Ssl\CertificateDetails;
use Illuminate\Support\Carbon;

/**
 * Real OpenSSL parsing/validation (openssl_x509_parse, openssl_x509_check_private_key)
 * - no network involved, so unlike the ACME client this is fully testable
 * end-to-end against genuine self-signed certificates generated in tests.
 */
class CertificateParserService
{
    public function parse(string $certificatePem): CertificateDetails
    {
        $parsed = openssl_x509_parse($certificatePem);

        if ($parsed === false) {
            throw new CertificateParserException('The provided certificate is not valid PEM-encoded X.509 data.');
        }

        $domains = $this->extractDomains($parsed);

        if ($domains === []) {
            throw new CertificateParserException('The certificate has no common name or subject alternative names.');
        }

        return new CertificateDetails(
            domains: $domains,
            issuedAt: Carbon::createFromTimestamp($parsed['validFrom_time_t']),
            expiresAt: Carbon::createFromTimestamp($parsed['validTo_time_t']),
        );
    }

    public function certificateMatchesPrivateKey(string $certificatePem, string $privateKeyPem): bool
    {
        return openssl_x509_check_private_key($certificatePem, $privateKeyPem);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<string>
     */
    private function extractDomains(array $parsed): array
    {
        $domains = [];

        if (! empty($parsed['subject']['CN'])) {
            $domains[] = $parsed['subject']['CN'];
        }

        $san = $parsed['extensions']['subjectAltName'] ?? null;

        if (is_string($san)) {
            foreach (explode(',', $san) as $entry) {
                $entry = trim($entry);

                if (str_starts_with($entry, 'DNS:')) {
                    $domains[] = substr($entry, 4);
                }
            }
        }

        return array_values(array_unique($domains));
    }
}
