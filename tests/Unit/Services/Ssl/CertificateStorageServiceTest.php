<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ssl;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use App\Services\Ssl\CertificateStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CertificateStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().'/mtp-ssl-storage-test-'.uniqid();
        config(['mtp.ssl_certificates_path' => $this->tempRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    public function test_it_writes_certificate_and_key_files_to_disk(): void
    {
        $website = $this->website();

        app(CertificateStorageService::class)->write($website, 'CERT-PEM-CONTENT', 'KEY-PEM-CONTENT');

        $this->assertFileExists("{$this->tempRoot}/{$website->domain}.crt");
        $this->assertFileExists("{$this->tempRoot}/{$website->domain}.key");
        $this->assertSame('CERT-PEM-CONTENT', File::get("{$this->tempRoot}/{$website->domain}.crt"));
    }

    public function test_it_removes_certificate_and_key_files(): void
    {
        $website = $this->website();
        $service = app(CertificateStorageService::class);

        $service->write($website, 'CERT', 'KEY');
        $service->remove($website);

        $this->assertFileDoesNotExist("{$this->tempRoot}/{$website->domain}.crt");
        $this->assertFileDoesNotExist("{$this->tempRoot}/{$website->domain}.key");
    }

    private function website(): Website
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => sys_get_temp_dir().'/mtp-ssl-doc-root',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);
    }
}
