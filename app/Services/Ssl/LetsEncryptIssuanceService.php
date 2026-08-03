<?php

declare(strict_types=1);

namespace App\Services\Ssl;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Models\SslCertificate;
use App\Models\Website;
use App\Services\Cloudflare\CloudflareZoneService;
use Illuminate\Support\Facades\File;

/**
 * Orchestrates a full ACME v2 issuance: account registration, order
 * creation, challenge fulfillment (http-01 by writing the well-known file
 * into the website's own document root; dns-01 via Cloudflare, for
 * wildcards), finalization, and certificate download.
 *
 * This cannot be verified end-to-end in this dev environment - Let's
 * Encrypt's servers validate domain control by connecting back to a public
 * IP/domain that does not exist here. See CLAUDE.md/docs/Security.md for the
 * full explanation; every ACME HTTP interaction is tested via `Http::fake()`
 * against real ACME v2 response shapes instead of a live round-trip.
 */
class LetsEncryptIssuanceService
{
    public function __construct(
        private readonly CertificateParserService $parser,
        private readonly CertificateStorageService $storage,
        private readonly CloudflareZoneService $cloudflareZones,
    ) {}

    /**
     * @param  list<string>  $domains
     */
    public function issue(Website $website, array $domains, bool $useDnsChallenge = false): SslCertificate
    {
        $certificate = SslCertificate::query()->create([
            'website_id' => $website->id,
            'type' => CertificateType::LetsEncrypt,
            'domains' => $domains,
            'status' => CertificateStatus::Pending,
        ]);

        try {
            $client = new AcmeClient($this->accountKey(), $this->storedAccountUrl());

            if ($this->storedAccountUrl() === null) {
                $this->storeAccountUrl($client->registerAccount());
            }

            $order = $client->createOrder($domains);

            foreach ($order['authorizations'] as $authorizationUrl) {
                $this->fulfillAuthorization($client, $website, $authorizationUrl, $useDnsChallenge);
            }

            [$csrDer, $domainKeyPem] = $this->generateCsr($domains);

            $client->finalizeOrder($order['finalize'], $csrDer);
            $finalizedOrder = $client->pollUntilNotPending($order['_orderUrl']);

            if (($finalizedOrder['status'] ?? null) !== 'valid') {
                throw new AcmeException('Order did not reach the valid state: '.($finalizedOrder['status'] ?? 'unknown'));
            }

            $certificatePem = $client->downloadCertificate($finalizedOrder['certificate']);
            $details = $this->parser->parse($certificatePem);

            $certificate->update([
                'certificate' => $certificatePem,
                'private_key' => $domainKeyPem,
                'issued_at' => $details->issuedAt,
                'expires_at' => $details->expiresAt,
                'status' => CertificateStatus::Active,
                'last_error' => null,
            ]);

            $this->storage->write($website, $certificatePem, $domainKeyPem);

            return $certificate->fresh();
        } catch (AcmeException $exception) {
            $certificate->update([
                'status' => CertificateStatus::Failed,
                'last_renewal_attempt_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function fulfillAuthorization(AcmeClient $client, Website $website, string $authorizationUrl, bool $useDnsChallenge): void
    {
        $authorization = $client->fetchAuthorization($authorizationUrl);
        $domain = $authorization['identifier']['value'];
        $challengeType = $useDnsChallenge ? 'dns-01' : 'http-01';

        $challenge = collect($authorization['challenges'])->firstWhere('type', $challengeType);

        if ($challenge === null) {
            throw new AcmeException("No {$challengeType} challenge offered for {$domain}.");
        }

        $keyAuthorization = $challenge['token'].'.'.$client->accountKeyThumbprint();

        if ($useDnsChallenge) {
            $this->fulfillDnsChallenge($website, $domain, $keyAuthorization);
        } else {
            $this->fulfillHttpChallenge($website, $challenge['token'], $keyAuthorization);
        }

        $client->notifyChallengeReady($challenge['url']);
        $client->pollUntilNotPending($authorizationUrl);
    }

    private function fulfillHttpChallenge(Website $website, string $token, string $keyAuthorization): void
    {
        $challengeDir = rtrim($website->publicPath(), '/').'/.well-known/acme-challenge';

        File::ensureDirectoryExists($challengeDir);
        File::put("{$challengeDir}/{$token}", $keyAuthorization);
    }

    private function fulfillDnsChallenge(Website $website, string $domain, string $keyAuthorization): void
    {
        $zone = $website->cloudflareZone;

        if ($zone === null) {
            throw new AcmeException('DNS-01 challenges require a connected Cloudflare zone (Module 9) for this website.');
        }

        $digest = rtrim(strtr(base64_encode(hash('sha256', $keyAuthorization, true)), '+/', '-_'), '=');

        $this->cloudflareZones->createDnsRecord($zone, 'TXT', "_acme-challenge.{$domain}", $digest, ttl: 60);
    }

    /**
     * @param  list<string>  $domains
     * @return array{0: string, 1: string} [CSR in DER form, domain private key PEM]
     */
    private function generateCsr(array $domains): array
    {
        $opensslConfigPath = config('mtp.openssl_config_path');
        $keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        if ($opensslConfigPath) {
            $keyOptions['config'] = $opensslConfigPath;
        }

        $key = openssl_pkey_new($keyOptions);

        if ($key === false) {
            throw new AcmeException('Could not generate a private key for the certificate signing request.');
        }

        openssl_pkey_export($key, $privateKeyPem, null, $opensslConfigPath ? ['config' => $opensslConfigPath] : []);

        $sanValue = collect($domains)->map(fn (string $domain): string => "DNS:{$domain}")->implode(',');
        $tempConfigPath = tempnam(sys_get_temp_dir(), 'mtp_acme_cnf_');
        File::put($tempConfigPath, <<<CNF
        [req]
        distinguished_name = req_distinguished_name
        req_extensions = v3_req
        [req_distinguished_name]
        [v3_req]
        subjectAltName = {$sanValue}
        CNF);

        $csr = openssl_csr_new(
            ['CN' => $domains[0]],
            $key,
            ['digest_alg' => 'sha256', 'config' => $tempConfigPath, 'req_extensions' => 'v3_req'],
        );

        File::delete($tempConfigPath);

        if ($csr === false) {
            throw new AcmeException('Could not generate a certificate signing request.');
        }

        openssl_csr_export($csr, $csrPem);

        return [$this->pemToDer($csrPem), $privateKeyPem];
    }

    private function pemToDer(string $pem): string
    {
        $lines = explode("\n", trim($pem));
        $body = implode('', array_slice($lines, 1, -1));

        return base64_decode($body);
    }

    private function accountKeyPath(): string
    {
        return storage_path('app/acme/account-key.pem');
    }

    private function accountUrlPath(): string
    {
        return storage_path('app/acme/account-url.txt');
    }

    private function accountKey(): string
    {
        if (File::exists($this->accountKeyPath())) {
            return File::get($this->accountKeyPath());
        }

        $opensslConfigPath = config('mtp.openssl_config_path');
        $keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        if ($opensslConfigPath) {
            $keyOptions['config'] = $opensslConfigPath;
        }

        $key = openssl_pkey_new($keyOptions);
        openssl_pkey_export($key, $pem, null, $opensslConfigPath ? ['config' => $opensslConfigPath] : []);

        File::ensureDirectoryExists(dirname($this->accountKeyPath()));
        File::put($this->accountKeyPath(), $pem);

        return $pem;
    }

    private function storedAccountUrl(): ?string
    {
        return File::exists($this->accountUrlPath()) ? File::get($this->accountUrlPath()) : null;
    }

    private function storeAccountUrl(string $url): void
    {
        File::ensureDirectoryExists(dirname($this->accountUrlPath()));
        File::put($this->accountUrlPath(), $url);
    }
}
