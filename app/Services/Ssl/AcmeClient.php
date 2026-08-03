<?php

declare(strict_types=1);

namespace App\Services\Ssl;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A minimal RFC 8555 (ACME v2) client, just enough to issue a certificate via
 * http-01 or dns-01 - not a full ACME conformance implementation (no account
 * key rollover, no external account binding, limited retry-on-badNonce
 * handling). Talks to Let's Encrypt's real, documented protocol - see
 * https://datatracker.ietf.org/doc/html/rfc8555 and
 * https://letsencrypt.org/docs/client-options/.
 *
 * This dev environment cannot complete a real issuance end-to-end: Let's
 * Encrypt's servers validate domain control by connecting back to a public
 * IP/domain, which does not exist here. Every method is tested with
 * `Http::fake()` against ACME v2's real response shapes instead - see
 * CLAUDE.md for the full reasoning (same pattern as Module 9's Cloudflare
 * client). Point config('mtp.acme_directory_url') at Let's Encrypt's
 * *staging* directory (the default) for any real-world testing before ever
 * using the production directory, to avoid production rate limits.
 */
class AcmeClient
{
    private ?string $nonce = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $directory = null;

    public function __construct(
        private readonly string $accountKeyPem,
        private ?string $accountUrl = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function directory(): array
    {
        if ($this->directory !== null) {
            return $this->directory;
        }

        $response = Http::get((string) config('mtp.acme_directory_url'));

        if (! $response->successful()) {
            throw new AcmeException('Could not fetch the ACME directory.');
        }

        return $this->directory = $response->json();
    }

    public function registerAccount(): string
    {
        $url = $this->directory()['newAccount'];

        $response = $this->signedRequest($url, ['termsOfServiceAgreed' => true], useJwk: true);

        $location = $response->header('Location');

        if (blank($location)) {
            throw new AcmeException('ACME newAccount response did not include a Location header.');
        }

        return $this->accountUrl = $location;
    }

    /**
     * @param  list<string>  $domains
     * @return array<string, mixed>
     */
    public function createOrder(array $domains): array
    {
        $url = $this->directory()['newOrder'];

        $identifiers = collect($domains)
            ->map(fn (string $domain): array => ['type' => 'dns', 'value' => $domain])
            ->all();

        $response = $this->signedRequest($url, ['identifiers' => $identifiers]);

        // The order's own URL (needed to poll its status later) is only ever
        // available via this response's Location header, never inside the
        // order body itself per RFC 8555 §7.4.
        return [...$response->json(), '_orderUrl' => $response->header('Location')];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAuthorization(string $authorizationUrl): array
    {
        $response = $this->signedRequest($authorizationUrl, null);

        return $response->json();
    }

    /**
     * Tells the ACME server this challenge is ready to be validated.
     *
     * @return array<string, mixed>
     */
    public function notifyChallengeReady(string $challengeUrl): array
    {
        $response = $this->signedRequest($challengeUrl, []);

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function pollUntilNotPending(string $url, int $maxAttempts = 10, int $delaySeconds = 1): array
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $result = $this->signedRequest($url, null)->json();

            if (($result['status'] ?? null) !== 'pending' && ($result['status'] ?? null) !== 'processing') {
                return $result;
            }

            if ($attempt < $maxAttempts - 1) {
                sleep($delaySeconds);
            }
        }

        throw new AcmeException("Timed out waiting for {$url} to leave the pending/processing state.");
    }

    /**
     * @return array<string, mixed>
     */
    public function finalizeOrder(string $finalizeUrl, string $csrDer): array
    {
        $response = $this->signedRequest($finalizeUrl, [
            'csr' => $this->base64UrlEncode($csrDer),
        ]);

        return $response->json();
    }

    public function downloadCertificate(string $certificateUrl): string
    {
        return $this->signedRequest($certificateUrl, null)->body();
    }

    /**
     * The RFC 7638 JWK thumbprint of the account key - the digest of its
     * canonical JSON form (keys sorted), used to build the key authorization
     * for both http-01 and dns-01 challenges.
     */
    public function accountKeyThumbprint(): string
    {
        $jwk = $this->jwk();
        ksort($jwk);

        return $this->base64UrlEncode(hash('sha256', json_encode($jwk, JSON_UNESCAPED_SLASHES), true));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function signedRequest(string $url, ?array $payload, bool $useJwk = false): Response
    {
        $nonce = $this->fetchNonce();

        $protected = [
            'alg' => 'RS256',
            'nonce' => $nonce,
            'url' => $url,
        ];

        if ($useJwk || $this->accountUrl === null) {
            $protected['jwk'] = $this->jwk();
        } else {
            $protected['kid'] = $this->accountUrl;
        }

        $encodedProtected = $this->base64UrlEncode(json_encode($protected, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $payload === null ? '' : $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = $this->sign("{$encodedProtected}.{$encodedPayload}");

        $response = Http::withHeaders(['Content-Type' => 'application/jose+json'])
            ->post($url, [
                'protected' => $encodedProtected,
                'payload' => $encodedPayload,
                'signature' => $this->base64UrlEncode($signature),
            ]);

        $this->nonce = $response->header('Replay-Nonce') ?: null;

        if (! $response->successful()) {
            $detail = $response->json('detail') ?? $response->body();

            throw new AcmeException("ACME request to {$url} failed: {$detail}");
        }

        return $response;
    }

    private function fetchNonce(): string
    {
        if ($this->nonce !== null) {
            $nonce = $this->nonce;
            $this->nonce = null;

            return $nonce;
        }

        $response = Http::head($this->directory()['newNonce']);
        $nonce = $response->header('Replay-Nonce');

        if (blank($nonce)) {
            throw new AcmeException('ACME server did not return a Replay-Nonce header.');
        }

        return $nonce;
    }

    /**
     * @return array<string, string>
     */
    private function jwk(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->accountKeyPem));

        return [
            'kty' => 'RSA',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    private function sign(string $data): string
    {
        openssl_sign($data, $signature, $this->accountKeyPem, OPENSSL_ALGO_SHA256);

        return $signature;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
