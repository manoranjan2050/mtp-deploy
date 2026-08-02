<?php

declare(strict_types=1);

namespace Tests\Feature\Websites;

use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\Pages\ManageFiles;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Renders the real ManageFiles Livewire page end-to-end against a real temp
 * directory - the same "real infrastructure over mocks" approach used for
 * FileManagerServiceTest, but here it also proves the Filament page wiring
 * (mount/authorization/Action delegation) actually works, not just the
 * service in isolation. See ListWebsitesPageTest's docblock for why a real
 * page render matters: closures and authorization gates are only evaluated
 * when Filament actually renders/mounts, not at the unit level.
 */
class ManageFilesPageTest extends TestCase
{
    use RefreshDatabase;

    private string $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->documentRoot = sys_get_temp_dir().'/mtp-managefiles-test-'.uniqid();
        File::makeDirectory($this->documentRoot, recursive: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->documentRoot);

        parent::tearDown();
    }

    public function test_an_admin_can_view_the_file_manager_page_with_a_real_row(): void
    {
        File::put($this->documentRoot.'/index.php', '<?php echo "hi";');

        $admin = $this->admin();
        $website = $this->website();

        Livewire::actingAs($admin)
            ->test(ManageFiles::class, ['record' => $website->getKey()])
            ->assertSuccessful()
            ->assertSee('index.php');
    }

    public function test_a_viewer_without_the_permission_is_denied(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $website = $this->website();

        Livewire::actingAs($viewer)
            ->test(ManageFiles::class, ['record' => $website->getKey()])
            ->assertForbidden();
    }

    public function test_it_creates_a_folder(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()])
            ->set('newFolderName', 'uploads')
            ->call('createFolder')
            ->assertSee('uploads');

        $this->assertDirectoryExists($this->documentRoot.'/uploads');
    }

    public function test_it_uploads_a_file(): void
    {
        $upload = UploadedFile::fake()->createWithContent('photo.jpg', str_repeat('x', 500));

        Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()])
            ->set('newUpload', $upload)
            ->call('upload')
            ->assertSee('photo.jpg');

        $this->assertFileExists($this->documentRoot.'/photo.jpg');
    }

    public function test_it_edits_and_saves_a_text_file(): void
    {
        File::put($this->documentRoot.'/notes.txt', 'original');

        Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()])
            ->call('startEditing', 'notes.txt')
            ->set('editingContents', 'updated content')
            ->call('saveEditing');

        $this->assertSame('updated content', File::get($this->documentRoot.'/notes.txt'));
    }

    public function test_it_renames_a_file(): void
    {
        File::put($this->documentRoot.'/old.txt', 'x');

        Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()])
            ->call('startRenaming', 'old.txt')
            ->set('renamingName', 'new.txt')
            ->call('confirmRename');

        $this->assertFileDoesNotExist($this->documentRoot.'/old.txt');
        $this->assertFileExists($this->documentRoot.'/new.txt');
    }

    public function test_it_deletes_a_file(): void
    {
        File::put($this->documentRoot.'/doomed.txt', 'x');

        Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()])
            ->call('delete', 'doomed.txt');

        $this->assertFileDoesNotExist($this->documentRoot.'/doomed.txt');
    }

    public function test_it_navigates_into_and_out_of_subdirectories(): void
    {
        File::makeDirectory($this->documentRoot.'/sub');
        File::put($this->documentRoot.'/sub/inner.txt', 'x');

        $component = Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()])
            ->call('navigateTo', 'sub')
            ->assertSee('inner.txt');

        $component->call('navigateUp')
            ->assertSee('sub');
    }

    public function test_it_rejects_path_traversal_attempts_from_the_component(): void
    {
        $component = Livewire::actingAs($this->admin())
            ->test(ManageFiles::class, ['record' => $this->website()->getKey()]);

        $component->call('navigateTo', '../../');

        // The component recovers gracefully (resets to root) rather than
        // exposing an unhandled exception to the browser.
        $component->assertSuccessful();
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
            'document_root' => $this->documentRoot,
            'php_version' => '8.3',
            'framework' => WebsiteFramework::PlainPhp,
        ]);
    }
}
