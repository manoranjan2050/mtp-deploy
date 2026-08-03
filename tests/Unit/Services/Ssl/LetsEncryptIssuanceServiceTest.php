<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ssl;

use App\Enums\CertificateStatus;
use App\Enums\WebsiteFramework;
use App\Models\CloudflareZone;
use App\Models\Server;
use App\Models\Website;
use App\Services\Ssl\AcmeException;
use App\Services\Ssl\LetsEncryptIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the full http-01 and dns-01 issuance orchestration against
 * Http::fake() responses shaped like Let's Encrypt's real ACME v2 protocol -
 * this cannot be a genuine end-to-end test (see AcmeClientTest's docblock),
 * but it proves every step wires together correctly: account registration,
 * order creation, challenge fulfillment (a real file written to a real
 * document root for http-01; a real Cloudflare API call for dns-01),
 * finalization, and certificate storage.
 */
class LetsEncryptIssuanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private string $sslStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRoot = sys_get_temp_dir().'/mtp-le-test-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);

        $this->sslStoragePath = sys_get_temp_dir().'/mtp-le-ssl-'.uniqid();
        config(['mtp.ssl_certificates_path' => $this->sslStoragePath]);

        File::delete(storage_path('app/acme/account-key.pem'));
        File::delete(storage_path('app/acme/account-url.txt'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);
        File::deleteDirectory($this->sslStoragePath);
        File::delete(storage_path('app/acme/account-key.pem'));
        File::delete(storage_path('app/acme/account-url.txt'));

        parent::tearDown();
    }

    public function test_it_issues_a_certificate_via_http_01_and_writes_the_challenge_file(): void
    {
        $this->fakeAcmeProtocol();

        $website = $this->website();

        $certificate = app(LetsEncryptIssuanceService::class)->issue($website, ['example.test']);

        $this->assertSame(CertificateStatus::Active, $certificate->status);
        $this->assertNotNull($certificate->certificate);
        $this->assertNotNull($certificate->private_key);
        $this->assertFileExists("{$this->sslStoragePath}/example.test.crt");

        // The http-01 challenge file was really written into the website's
        // own document root, with the exact key authorization content ACME
        // expects there.
        $challengeFiles = glob("{$website->publicPath()}/.well-known/acme-challenge/*");
        $this->assertNotEmpty($challengeFiles);
        $this->assertStringContainsString('test-token-123.', File::get($challengeFiles[0]));
    }

    public function test_it_issues_a_wildcard_certificate_via_dns_01_using_the_connected_cloudflare_zone(): void
    {
        $this->fakeAcmeProtocol(challengeType: 'dns-01');

        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'errors' => [],
                'result' => ['id' => 'rec1', 'type' => 'TXT', 'name' => '_acme-challenge.example.test', 'content' => 'digest', 'ttl' => 60, 'proxied' => false],
            ]),
        ]);

        $website = $this->website();
        CloudflareZone::query()->create(['website_id' => $website->id, 'zone_id' => 'zone123', 'api_token' => 'token123']);

        $certificate = app(LetsEncryptIssuanceService::class)->issue($website, ['*.example.test'], useDnsChallenge: true);

        $this->assertSame(CertificateStatus::Active, $certificate->status);

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'dns_records')
            && ($request['type'] ?? null) === 'TXT');
    }

    public function test_dns_01_without_a_connected_zone_fails_honestly(): void
    {
        $this->fakeAcmeProtocol(challengeType: 'dns-01');

        $website = $this->website();

        $this->expectException(AcmeException::class);

        try {
            app(LetsEncryptIssuanceService::class)->issue($website, ['*.example.test'], useDnsChallenge: true);
        } finally {
            $this->assertSame(CertificateStatus::Failed, $website->certificates()->first()->status);
        }
    }

    private function fakeAcmeProtocol(string $challengeType = 'http-01'): void
    {
        // The parser needs a real, parseable certificate for the download
        // step, so a genuine self-signed one is generated up front.
        [$certPem] = $this->generateSelfSignedCertificate(['example.test', '*.example.test']);

        Http::fake([
            'acme-staging-v02.api.letsencrypt.org/directory' => Http::response([
                'newNonce' => 'https://acme.test/new-nonce',
                'newAccount' => 'https://acme.test/new-account',
                'newOrder' => 'https://acme.test/new-order',
            ]),
            'acme.test/new-nonce' => Http::response('', 200, ['Replay-Nonce' => 'n1']),
            'acme.test/new-account' => Http::response(
                ['status' => 'valid'],
                201,
                ['Location' => 'https://acme.test/account/1', 'Replay-Nonce' => 'n2'],
            ),
            'acme.test/new-order' => Http::response(
                ['status' => 'pending', 'authorizations' => ['https://acme.test/authz/1'], 'finalize' => 'https://acme.test/finalize/1'],
                201,
                ['Location' => 'https://acme.test/order/1', 'Replay-Nonce' => 'n3'],
            ),
            'acme.test/authz/1' => Http::sequence()
                ->push([
                    'status' => 'pending',
                    'identifier' => ['type' => 'dns', 'value' => 'example.test'],
                    'challenges' => [
                        ['type' => $challengeType, 'url' => 'https://acme.test/challenge/1', 'token' => 'test-token-123'],
                    ],
                ], 200, ['Replay-Nonce' => 'n4'])
                ->push(['status' => 'valid'], 200, ['Replay-Nonce' => 'n5']),
            'acme.test/challenge/1' => Http::response(['status' => 'pending'], 200, ['Replay-Nonce' => 'n6']),
            'acme.test/finalize/1' => Http::response(['status' => 'processing'], 200, ['Replay-Nonce' => 'n7']),
            'acme.test/order/1' => Http::response(
                ['status' => 'valid', 'certificate' => 'https://acme.test/cert/1'],
                200,
                ['Replay-Nonce' => 'n8'],
            ),
            'acme.test/cert/1' => Http::response($certPem, 200, ['Replay-Nonce' => 'n9']),
        ]);
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

        $csr = openssl_csr_new(['CN' => $domains[0]], $key, ['digest_alg' => 'sha256', 'config' => $tempConfigPath, 'x509_extensions' => 'v3_ca']);
        $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256', 'config' => $tempConfigPath, 'x509_extensions' => 'v3_ca']);

        openssl_x509_export($x509, $certificatePem);
        unlink($tempConfigPath);

        return [$certificatePem, $privateKeyPem];
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::PlainPhp,
        ]);
    }
}
