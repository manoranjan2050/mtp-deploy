<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FileManager;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\Website;
use App\Services\FileManager\FileManagerException;
use App\Services\FileManager\FileManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Exercises FileManagerService against a real temp directory on disk (not a
 * mocked filesystem) - this is the module that most needs a genuine
 * `realpath()`/OS-level check, since the whole point is that path-traversal
 * protection actually holds up against real filesystem resolution rules, not
 * just against a stubbed-out assertion.
 */
class FileManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    private ?Website $website = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRoot = sys_get_temp_dir().'/mtp-filemanager-test-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);

        parent::tearDown();
    }

    public function test_it_lists_files_and_directories_sorted_with_directories_first(): void
    {
        File::put($this->documentRoot.'/b.txt', 'b');
        File::put($this->documentRoot.'/a.txt', 'a');
        File::makeDirectory($this->documentRoot.'/z-folder');

        $entries = $this->service()->list();

        $this->assertSame(['z-folder', 'a.txt', 'b.txt'], $entries->pluck('name')->all());
        $this->assertTrue($entries->first()->isDirectory);
    }

    public function test_it_creates_a_directory(): void
    {
        $this->service()->createDirectory('', 'uploads');

        $this->assertDirectoryExists($this->documentRoot.'/uploads');
    }

    public function test_it_writes_and_reads_text_files(): void
    {
        $this->service()->writeText('config.php', "<?php\n// hello\n");

        $this->assertSame("<?php\n// hello\n", $this->service()->readText('config.php'));
    }

    public function test_it_uploads_a_file(): void
    {
        $upload = UploadedFile::fake()->createWithContent('avatar.png', str_repeat('x', 100));

        $this->service()->upload('', $upload);

        $this->assertFileExists($this->documentRoot.'/avatar.png');
    }

    public function test_it_renames_a_file(): void
    {
        File::put($this->documentRoot.'/old.txt', 'content');

        $this->service()->rename('old.txt', 'new.txt');

        $this->assertFileDoesNotExist($this->documentRoot.'/old.txt');
        $this->assertFileExists($this->documentRoot.'/new.txt');
    }

    public function test_it_deletes_a_file(): void
    {
        File::put($this->documentRoot.'/doomed.txt', 'x');

        $this->service()->delete('doomed.txt');

        $this->assertFileDoesNotExist($this->documentRoot.'/doomed.txt');
    }

    public function test_it_deletes_a_directory_recursively(): void
    {
        File::makeDirectory($this->documentRoot.'/dir/nested', recursive: true);
        File::put($this->documentRoot.'/dir/nested/file.txt', 'x');

        $this->service()->delete('dir');

        $this->assertDirectoryDoesNotExist($this->documentRoot.'/dir');
    }

    public function test_it_zips_and_unzips_a_directory_round_trip(): void
    {
        File::makeDirectory($this->documentRoot.'/site', recursive: true);
        File::put($this->documentRoot.'/site/index.php', '<?php echo "hi";');

        $service = $this->service();
        $service->zip('', ['site'], 'site.zip');

        $this->assertFileExists($this->documentRoot.'/site.zip');

        File::deleteDirectory($this->documentRoot.'/site');
        $this->assertDirectoryDoesNotExist($this->documentRoot.'/site');

        $service->unzip('site.zip');

        $this->assertFileExists($this->documentRoot.'/site/index.php');
        $this->assertSame('<?php echo "hi";', $service->readText('site/index.php'));
    }

    public function test_it_rejects_dot_dot_path_traversal(): void
    {
        $this->expectException(FileManagerException::class);

        $this->service()->readText('../outside.txt');
    }

    public function test_it_rejects_absolute_paths(): void
    {
        $this->expectException(FileManagerException::class);

        $this->service()->readText('C:/Windows/win.ini');
    }

    public function test_it_rejects_null_bytes(): void
    {
        $this->expectException(FileManagerException::class);

        $this->service()->readText("safe.txt\0.php");
    }

    public function test_it_rejects_a_resolved_path_that_escapes_the_document_root(): void
    {
        // A sibling directory that genuinely exists on disk - this proves the
        // containment check works against a real resolved realpath(), not
        // just against the raw string (which would already have been caught
        // by the ".." check on a naive "../secret" input alone).
        $sibling = dirname($this->documentRoot).'/mtp-filemanager-sibling-'.uniqid();
        File::makeDirectory($sibling);
        File::put($sibling.'/secret.txt', 'top secret');

        $relativeEscape = '../'.basename($sibling).'/secret.txt';

        $this->expectException(FileManagerException::class);

        try {
            $this->service()->readText($relativeEscape);
        } finally {
            File::deleteDirectory($sibling);
        }
    }

    public function test_it_rejects_unsafe_filenames_on_rename(): void
    {
        File::put($this->documentRoot.'/file.txt', 'x');

        $this->expectException(FileManagerException::class);

        $this->service()->rename('file.txt', '../escaped.txt');
    }

    public function test_unzip_guards_against_zip_slip(): void
    {
        $maliciousZipPath = $this->documentRoot.'/malicious.zip';

        $zip = new ZipArchive;
        $zip->open($maliciousZipPath, ZipArchive::CREATE);
        $zip->addFromString('../escaped.txt', 'should not land outside the document root');
        $zip->close();

        $this->service()->unzip('malicious.zip');

        $this->assertFileDoesNotExist(dirname($this->documentRoot).'/escaped.txt');
        $this->assertFileDoesNotExist($this->documentRoot.'/../escaped.txt');
    }

    public function test_unzip_rejects_an_archive_with_an_implausible_compression_ratio(): void
    {
        $bombZipPath = $this->documentRoot.'/bomb.zip';

        $zip = new ZipArchive;
        $zip->open($bombZipPath, ZipArchive::CREATE);
        // Highly compressible content (a single repeated byte) compresses to
        // a tiny fraction of its uncompressed size - a real decompression
        // bomb's signature, unlike genuine mixed-content files.
        $zip->addFromString('huge.txt', str_repeat('0', 5 * 1024 * 1024));
        $zip->close();

        $this->expectException(FileManagerException::class);

        $this->service()->unzip('bomb.zip');
    }

    private function service(): FileManagerService
    {
        return new FileManagerService($this->website());
    }

    private function website(): Website
    {
        if ($this->website !== null) {
            return $this->website;
        }

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return $this->website = Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::PlainPhp,
        ]);
    }
}
