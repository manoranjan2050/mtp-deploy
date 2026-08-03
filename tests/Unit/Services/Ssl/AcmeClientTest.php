<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ssl;

use App\Services\Ssl\AcmeClient;
use App\Services\Ssl\AcmeException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the JWS signing/protocol logic against Http::fake() responses
 * shaped exactly like Let's Encrypt's real, documented ACME v2 API
 * (https://datatracker.ietf.org/doc/html/rfc8555) - this dev environment
 * cannot complete a real issuance (Let's Encrypt validates domain control by
 * connecting back to a public IP/domain that doesn't exist here), so these
 * tests instead prove the client builds a *correctly signed* JWS request
 * (verified by actually verifying the signature against the account's own
 * public key, not just asserting a header exists) and parses real ACME
 * response shapes correctly. See CLAUDE.md.
 */
class AcmeClientTest extends TestCase
{
    private string $accountKeyPem = '';

    protected function setUp(): void
    {
        parent::setUp();

        $configPath = config('mtp.openssl_config_path');
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        if ($configPath) {
            $options['config'] = $configPath;
        }

        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $this->accountKeyPem, null, $configPath ? ['config' => $configPath] : []);
    }

    public function test_it_fetches_the_directory(): void
    {
        Http::fake([
            'acme-staging-v02.api.letsencrypt.org/directory' => Http::response([
                'newNonce' => 'https://acme.test/new-nonce',
                'newAccount' => 'https://acme.test/new-account',
                'newOrder' => 'https://acme.test/new-order',
            ]),
        ]);

        $directory = (new AcmeClient($this->accountKeyPem))->directory();

        $this->assertSame('https://acme.test/new-nonce', $directory['newNonce']);
    }

    public function test_it_registers_an_account_with_a_validly_signed_jws_request(): void
    {
        $this->fakeDirectory();

        Http::fake([
            'acme.test/new-account' => Http::response(
                ['status' => 'valid'],
                201,
                ['Location' => 'https://acme.test/account/123', 'Replay-Nonce' => 'nonce-2'],
            ),
        ]);

        $accountUrl = (new AcmeClient($this->accountKeyPem))->registerAccount();

        $this->assertSame('https://acme.test/account/123', $accountUrl);

        Http::assertSent(function ($request): bool {
            if (! str_contains((string) $request->url(), 'new-account')) {
                return false;
            }

            $body = $request->data();
            $this->assertJwsIsValidlySigned($body);

            $protected = json_decode($this->base64UrlDecode($body['protected']), true);
            $this->assertArrayHasKey('jwk', $protected);
            $this->assertSame('https://acme.test/new-account', $protected['url']);

            return true;
        });
    }

    public function test_it_creates_an_order_and_captures_the_order_url_from_the_location_header(): void
    {
        $this->fakeDirectory();
        $this->fakeAccountRegistration();

        Http::fake([
            'acme.test/new-order' => Http::response(
                ['status' => 'pending', 'authorizations' => ['https://acme.test/authz/1'], 'finalize' => 'https://acme.test/finalize/1'],
                201,
                ['Location' => 'https://acme.test/order/1', 'Replay-Nonce' => 'nonce-3'],
            ),
        ]);

        $client = new AcmeClient($this->accountKeyPem);
        $client->registerAccount();

        $order = $client->createOrder(['example.com']);

        $this->assertSame('https://acme.test/order/1', $order['_orderUrl']);
        $this->assertSame(['https://acme.test/authz/1'], $order['authorizations']);
    }

    public function test_it_computes_the_correct_rfc7638_jwk_thumbprint(): void
    {
        $client = new AcmeClient($this->accountKeyPem);

        $thumbprint = $client->accountKeyThumbprint();

        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->accountKeyPem));
        $jwk = [
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            'kty' => 'RSA',
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
        ];
        $expected = rtrim(strtr(base64_encode(hash('sha256', json_encode($jwk), true)), '+/', '-_'), '=');

        $this->assertSame($expected, $thumbprint);
    }

    public function test_a_failed_request_throws_with_the_acme_error_detail(): void
    {
        $this->fakeDirectory();

        Http::fake([
            'acme.test/new-account' => Http::response(['type' => 'urn:ietf:params:acme:error:malformed', 'detail' => 'Invalid contact'], 400),
        ]);

        $this->expectException(AcmeException::class);
        $this->expectExceptionMessage('Invalid contact');

        (new AcmeClient($this->accountKeyPem))->registerAccount();
    }

    private function fakeDirectory(): void
    {
        Http::fake([
            'acme-staging-v02.api.letsencrypt.org/directory' => Http::response([
                'newNonce' => 'https://acme.test/new-nonce',
                'newAccount' => 'https://acme.test/new-account',
                'newOrder' => 'https://acme.test/new-order',
            ]),
            'acme.test/new-nonce' => Http::response('', 200, ['Replay-Nonce' => 'nonce-1']),
        ]);
    }

    private function fakeAccountRegistration(): void
    {
        Http::fake([
            'acme.test/new-account' => Http::response(
                ['status' => 'valid'],
                201,
                ['Location' => 'https://acme.test/account/123', 'Replay-Nonce' => 'nonce-2'],
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function assertJwsIsValidlySigned(array $body): void
    {
        $signingInput = "{$body['protected']}.{$body['payload']}";
        $signature = $this->base64UrlDecode($body['signature']);

        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->accountKeyPem));
        $publicKey = openssl_pkey_get_public($details['key']);
        $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        $this->assertSame(1, $verified, 'The JWS signature does not verify against the account public key.');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
