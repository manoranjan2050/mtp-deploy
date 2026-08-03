<?php

declare(strict_types=1);

namespace Tests\Feature\Servers;

use App\Filament\Resources\Servers\Pages\CreateServer;
use App\Filament\Resources\Servers\Pages\EditServer;
use App\Filament\Resources\Servers\Pages\ListServers;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServerResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_an_admin_can_create_a_server(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateServer::class)
            ->fillForm([
                'name' => 'New Remote Server',
                'ssh_host' => 'remote.example.test',
                'ssh_port' => 22,
                'ssh_user' => 'deploy',
                'ssh_private_key' => "-----BEGIN RSA PRIVATE KEY-----\nfake\n-----END RSA PRIVATE KEY-----",
                'tags' => ['production'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('servers', ['name' => 'New Remote Server', 'created_by' => $admin->id]);
    }

    public function test_a_developer_cannot_access_the_list_page(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        Livewire::actingAs($developer)
            ->test(ListServers::class)
            ->assertForbidden();
    }

    public function test_an_admin_can_view_and_update_a_server(): void
    {
        $admin = $this->admin();
        $server = Server::query()->create([
            'name' => 'Existing Server',
            'ssh_host' => 'existing.test',
            'ssh_user' => 'deploy',
            'ssh_private_key' => 'placeholder',
        ]);

        Livewire::actingAs($admin)
            ->test(EditServer::class, ['record' => $server->getKey()])
            ->assertSuccessful()
            ->fillForm(['name' => 'Renamed Server'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Server', $server->fresh()->name);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}
