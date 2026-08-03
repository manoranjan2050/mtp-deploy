<?php

declare(strict_types=1);

namespace Tests\Feature\Docker;

use App\Filament\Pages\Docker;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DockerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Server::query()->create(['name' => 'Local Server', 'is_local' => true]);
    }

    public function test_an_admin_can_view_containers_and_images(): void
    {
        Http::fake([
            '*/containers/json*' => Http::response([
                ['Id' => 'abc123', 'Names' => ['/my-app'], 'Image' => 'nginx:latest', 'State' => 'running', 'Status' => 'Up 2 hours'],
            ]),
            '*/images/json' => Http::response([
                ['Id' => 'sha256:xyz', 'RepoTags' => ['nginx:latest'], 'Size' => 142000000],
            ]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(Docker::class)
            ->assertSuccessful()
            ->assertSee('my-app')
            ->assertSee('nginx:latest');
    }

    public function test_it_honestly_reports_an_unreachable_docker_daemon(): void
    {
        Http::fake([
            '*/containers/json*' => Http::response(['message' => 'connection refused'], 500),
            '*/images/json' => Http::response([]),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(Docker::class)
            ->assertSuccessful()
            ->assertSee('Could not reach the Docker Engine API');
    }

    public function test_a_developer_cannot_access_the_page(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        Livewire::actingAs($developer)
            ->test(Docker::class)
            ->assertForbidden();
    }

    public function test_an_admin_can_start_a_container(): void
    {
        Http::fake([
            '*/containers/json*' => Http::response([]),
            '*/images/json' => Http::response([]),
            '*/containers/abc123/start' => Http::response('', 204),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(Docker::class)
            ->call('startContainer', 'abc123')
            ->assertNotified();
    }

    public function test_an_admin_can_pull_an_image(): void
    {
        Http::fake([
            '*/containers/json*' => Http::response([]),
            '*/images/json' => Http::response([]),
            '*/images/create*' => Http::response('', 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(Docker::class)
            ->set('imageName', 'redis:latest')
            ->call('pullImage')
            ->assertNotified();

        $this->assertDatabaseHas('activity_log', ['log_name' => 'docker', 'description' => 'pulled image']);
    }
}
