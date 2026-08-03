<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Backups;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use App\Services\Backups\WebsiteFileBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebsiteFileBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private string $backupsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRoot = sys_get_temp_dir().'/mtp-backup-docroot-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);
        File::put("{$this->documentRoot}/index.php", '<?php echo "hi";');
        File::makeDirectory("{$this->documentRoot}/assets");
        File::put("{$this->documentRoot}/assets/app.css", 'body{}');

        $this->backupsPath = sys_get_temp_dir().'/mtp-backup-storage-'.uniqid();
        config(['mtp.website_backups_path' => $this->backupsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);
        File::deleteDirectory($this->backupsPath);

        parent::tearDown();
    }

    public function test_it_backs_up_and_restores_a_real_document_root(): void
    {
        $website = $this->website();
        $service = app(WebsiteFileBackupService::class);

        $archivePath = $service->backup($website);

        $this->assertFileExists($archivePath);

        File::deleteDirectory($this->documentRoot);
        $this->assertDirectoryDoesNotExist($this->documentRoot);

        $service->restore($website, $archivePath);

        $this->assertFileExists("{$this->documentRoot}/index.php");
        $this->assertFileExists("{$this->documentRoot}/assets/app.css");
        $this->assertSame('<?php echo "hi";', File::get("{$this->documentRoot}/index.php"));
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
