<?php

declare(strict_types=1);

namespace Tests\Feature\Ssl;

use App\Actions\Ssl\IssueLetsEncryptCertificateAction;
use App\Actions\Ssl\RenewCertificateAction;
use App\Actions\Ssl\RevokeCertificateAction;
use App\Actions\Ssl\UploadCustomCertificateAction;
use App\Enums\CertificateStatus;
use App\Enums\SslStatus;
use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use App\Services\Ssl\AcmeException;
use App\Services\Ssl\CertificateParserException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SslActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private string $sslStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->documentRoot = sys_get_temp_dir().'/mtp-ssl-actions-test-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);

        $this->sslStoragePath = sys_get_temp_dir().'/mtp-ssl-actions-storage-'.uniqid();
        config(['mtp.ssl_certificates_path' => $this->sslStoragePath]);

        File::delete(storage_path('app/acme/account-key.pem'));
        File::delete(storage_path('app/acme/account-url.txt'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);
        File::deleteDirectory($this->sslStoragePath);

        parent::tearDown();
    }

    public function test_uploading_a_custom_certificate_activates_it_and_syncs_website_ssl_status(): void
    {
        [$certPem, $keyPem] = $this->generateSelfSignedCertificate(['example.test']);
        $website = $this->website();

        $certificate = app(UploadCustomCertificateAction::class)->handle($website, $certPem, $keyPem);

        $this->assertSame(CertificateStatus::Active, $certificate->status);
        $this->assertSame(SslStatus::Active, $website->fresh()->ssl_status);
        $this->assertFileExists("{$this->sslStoragePath}/example.test.crt");
    }

    public function test_uploading_a_mismatched_key_is_rejected(): void
    {
        [$certPem] = $this->generateSelfSignedCertificate(['example.test']);
        [, $wrongKeyPem] = $this->generateSelfSignedCertificate(['other.test']);

        $this->expectException(CertificateParserException::class);

        app(UploadCustomCertificateAction::class)->handle($this->website(), $certPem, $wrongKeyPem);
    }

    public function test_issuing_a_lets_encrypt_certificate_syncs_ssl_status_on_success(): void
    {
        $this->fakeAcmeProtocol();
        $website = $this->website();

        $certificate = app(IssueLetsEncryptCertificateAction::class)->handle($website, ['example.test']);

        $this->assertSame(CertificateStatus::Active, $certificate->status);
        $this->assertSame(SslStatus::Active, $website->fresh()->ssl_status);
    }

    public function test_a_failed_issuance_marks_the_website_ssl_status_pending_not_active(): void
    {
        Http::fake([
            'acme-staging-v02.api.letsencrypt.org/directory' => Http::response(['newNonce' => 'https://acme.test/new-nonce', 'newAccount' => 'https://acme.test/new-account', 'newOrder' => 'https://acme.test/new-order']),
            'acme.test/new-nonce' => Http::response('', 200, ['Replay-Nonce' => 'n1']),
            'acme.test/new-account' => Http::response(['status' => 'invalid'], 400, ['Replay-Nonce' => 'n2']),
        ]);

        $website = $this->website();

        try {
            app(IssueLetsEncryptCertificateAction::class)->handle($website, ['example.test']);
            $this->fail('Expected an AcmeException to be thrown.');
        } catch (AcmeException) {
            // expected
        }

        $this->assertSame(SslStatus::Pending, $website->fresh()->ssl_status);
    }

    public function test_revoking_a_certificate_removes_disk_files_and_resets_website_ssl_status(): void
    {
        [$certPem, $keyPem] = $this->generateSelfSignedCertificate(['example.test']);
        $website = $this->website();
        $certificate = app(UploadCustomCertificateAction::class)->handle($website, $certPem, $keyPem);

        app(RevokeCertificateAction::class)->handle($certificate);

        $this->assertSame(CertificateStatus::Revoked, $certificate->fresh()->status);
        $this->assertSame(SslStatus::None, $website->fresh()->ssl_status);
        $this->assertFileDoesNotExist("{$this->sslStoragePath}/example.test.crt");
    }

    public function test_renewing_a_custom_certificate_is_rejected(): void
    {
        [$certPem, $keyPem] = $this->generateSelfSignedCertificate(['example.test']);
        $certificate = app(UploadCustomCertificateAction::class)->handle($this->website(), $certPem, $keyPem);

        $this->expectException(\InvalidArgumentException::class);

        app(RenewCertificateAction::class)->handle($certificate);
    }

    public function test_renewing_a_lets_encrypt_certificate_issues_a_fresh_one(): void
    {
        $this->fakeAcmeProtocol(issuances: 2);
        $website = $this->website();

        $original = app(IssueLetsEncryptCertificateAction::class)->handle($website, ['example.test']);
        $renewed = app(RenewCertificateAction::class)->handle($original);

        $this->assertNotSame($original->id, $renewed->id);
        $this->assertSame(CertificateStatus::Active, $renewed->status);
    }

    private function fakeAcmeProtocol(int $issuances = 1): void
    {
        [$certPem] = $this->generateSelfSignedCertificate(['example.test']);

        $authzSequence = Http::sequence();
        $certSequence = Http::sequence();

        for ($i = 0; $i < $issuances; $i++) {
            $authzSequence
                ->push(['status' => 'pending', 'identifier' => ['type' => 'dns', 'value' => 'example.test'], 'challenges' => [['type' => 'http-01', 'url' => 'https://acme.test/challenge/1', 'token' => 'tok']]], 200, ['Replay-Nonce' => 'n4'])
                ->push(['status' => 'valid'], 200, ['Replay-Nonce' => 'n5']);

            $certSequence->push($certPem, 200, ['Replay-Nonce' => 'n9']);
        }

        Http::fake([
            'acme-staging-v02.api.letsencrypt.org/directory' => Http::response([
                'newNonce' => 'https://acme.test/new-nonce',
                'newAccount' => 'https://acme.test/new-account',
                'newOrder' => 'https://acme.test/new-order',
            ]),
            'acme.test/new-nonce' => Http::response('', 200, ['Replay-Nonce' => 'n1']),
            'acme.test/new-account' => Http::response(['status' => 'valid'], 201, ['Location' => 'https://acme.test/account/1', 'Replay-Nonce' => 'n2']),
            'acme.test/new-order' => Http::response(
                ['status' => 'pending', 'authorizations' => ['https://acme.test/authz/1'], 'finalize' => 'https://acme.test/finalize/1'],
                201,
                ['Location' => 'https://acme.test/order/1', 'Replay-Nonce' => 'n3'],
            ),
            'acme.test/authz/1' => $authzSequence,
            'acme.test/challenge/1' => Http::response(['status' => 'pending'], 200, ['Replay-Nonce' => 'n6']),
            'acme.test/finalize/1' => Http::response(['status' => 'processing'], 200, ['Replay-Nonce' => 'n7']),
            'acme.test/order/1' => Http::response(['status' => 'valid', 'certificate' => 'https://acme.test/cert/1'], 200, ['Replay-Nonce' => 'n8']),
            'acme.test/cert/1' => $certSequence,
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
