<?php

declare(strict_types=1);

namespace Tests\Feature\Ssl;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageSsl;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSslPageTest extends TestCase
{
    use RefreshDatabase;

    private string $sslStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->sslStoragePath = sys_get_temp_dir().'/mtp-manage-ssl-test-'.uniqid();
        config(['mtp.ssl_certificates_path' => $this->sslStoragePath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sslStoragePath);

        parent::tearDown();
    }

    public function test_it_shows_no_certificate_and_accepts_a_custom_upload(): void
    {
        [$certPem, $keyPem] = $this->generateSelfSignedCertificate(['example.test']);

        Livewire::actingAs($this->admin())
            ->test(ManageSsl::class, ['record' => $this->website()->getKey()])
            ->assertSuccessful()
            ->assertSee('No active certificate')
            ->set('uploadCertificatePem', $certPem)
            ->set('uploadPrivateKeyPem', $keyPem)
            ->call('uploadCustom')
            ->assertSee('example.test');
    }

    public function test_a_viewer_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ManageSsl::class, ['record' => $this->website()->getKey()])
            ->assertForbidden();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => sys_get_temp_dir().'/mtp-manage-ssl-docroot',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
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
}
