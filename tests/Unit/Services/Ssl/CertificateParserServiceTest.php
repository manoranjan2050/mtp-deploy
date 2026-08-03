<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ssl;

use App\Services\Ssl\CertificateParserException;
use App\Services\Ssl\CertificateParserService;
use Tests\TestCase;

/**
 * Exercises real OpenSSL parsing against a genuinely generated self-signed
 * certificate - no network involved, so unlike the ACME client this can (and
 * should) be tested for real end-to-end on this machine.
 */
class CertificateParserServiceTest extends TestCase
{
    public function test_it_parses_domains_and_expiry_from_a_real_certificate(): void
    {
        [$certificatePem, $privateKeyPem] = $this->generateSelfSignedCertificate(['example.com', 'www.example.com']);

        $details = app(CertificateParserService::class)->parse($certificatePem);

        $this->assertContains('example.com', $details->domains);
        $this->assertContains('www.example.com', $details->domains);
        $this->assertTrue($details->expiresAt->isFuture());
    }

    public function test_it_confirms_a_matching_certificate_and_private_key(): void
    {
        [$certificatePem, $privateKeyPem] = $this->generateSelfSignedCertificate(['example.com']);

        $this->assertTrue(app(CertificateParserService::class)->certificateMatchesPrivateKey($certificatePem, $privateKeyPem));
    }

    public function test_it_detects_a_mismatched_private_key(): void
    {
        [$certificatePem] = $this->generateSelfSignedCertificate(['example.com']);
        [, $otherPrivateKeyPem] = $this->generateSelfSignedCertificate(['other.com']);

        $this->assertFalse(app(CertificateParserService::class)->certificateMatchesPrivateKey($certificatePem, $otherPrivateKeyPem));
    }

    public function test_it_rejects_garbage_input(): void
    {
        $this->expectException(CertificateParserException::class);

        app(CertificateParserService::class)->parse('not a certificate');
    }

    /**
     * @param  list<string>  $domains
     * @return array{0: string, 1: string}
     */
    private function generateSelfSignedCertificate(array $domains): array
    {
        $configPath = config('mtp.openssl_config_path');
        $keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        if ($configPath) {
            $keyOptions['config'] = $configPath;
        }

        $key = openssl_pkey_new($keyOptions);
        openssl_pkey_export($key, $privateKeyPem, null, $configPath ? ['config' => $configPath] : []);

        $sanValue = collect($domains)->map(fn (string $domain): string => "DNS:{$domain}")->implode(',');
        $tempConfigPath = tempnam(sys_get_temp_dir(), 'mtp_test_cnf_');
        file_put_contents($tempConfigPath, <<<CNF
        [req]
        distinguished_name = req_distinguished_name
        x509_extensions = v3_ca
        [req_distinguished_name]
        [v3_ca]
        subjectAltName = {$sanValue}
        CNF);

        $csr = openssl_csr_new(
            ['CN' => $domains[0]],
            $key,
            ['digest_alg' => 'sha256', 'config' => $tempConfigPath, 'x509_extensions' => 'v3_ca'],
        );

        $x509 = openssl_csr_sign($csr, null, $key, 365, [
            'digest_alg' => 'sha256',
            'config' => $tempConfigPath,
            'x509_extensions' => 'v3_ca',
        ]);

        openssl_x509_export($x509, $certificatePem);
        unlink($tempConfigPath);

        return [$certificatePem, $privateKeyPem];
    }
}
