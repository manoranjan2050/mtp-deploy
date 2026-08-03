<?php

declare(strict_types=1);

namespace Tests\Feature\Terminal;

use App\Filament\Pages\Terminal;
use App\Models\Server;
use App\Models\TerminalSession;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Renders the real Terminal Livewire page end-to-end, same "real
 * infrastructure over mocks" + "render the real page, not just the service"
 * pattern used by ManageFilesPageTest (Module 7) and ListWebsitesPageTest
 * (Module 5).
 */
class TerminalPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
    }

    public function test_an_admin_can_view_the_page_and_open_a_tab(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Terminal::class)
            ->assertSuccessful()
            ->call('openNewTab');

        $this->assertCount(1, TerminalSession::query()->get());
    }

    public function test_running_a_command_persists_history_and_is_returned_to_the_caller(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Terminal::class);
        $component->call('openNewTab');
        $sessionId = TerminalSession::query()->first()->id;

        $response = $component->instance()->runCommand($sessionId, 'echo hello-terminal');

        $this->assertStringContainsString('hello-terminal', $response['output']);
        $this->assertFalse($response['requiresConfirmation']);
    }

    public function test_a_dangerous_command_requires_typing_yes_to_confirm(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Terminal::class);
        $component->call('openNewTab');
        $sessionId = TerminalSession::query()->first()->id;

        $blocked = $component->instance()->runCommand($sessionId, 'DROP DATABASE production');
        $this->assertTrue($blocked['requiresConfirmation']);

        $confirmed = $component->instance()->runCommand($sessionId, 'yes');
        $this->assertFalse($confirmed['requiresConfirmation']);
    }

    public function test_closing_a_tab_removes_it_from_open_sessions(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Terminal::class);
        $component->call('openNewTab');
        $sessionId = TerminalSession::query()->first()->id;

        $component->call('closeTab', $sessionId);

        $this->assertNotNull(TerminalSession::query()->find($sessionId)->closed_at);
    }

    public function test_a_developer_cannot_access_the_terminal_page(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        Livewire::actingAs($developer)
            ->test(Terminal::class)
            ->assertForbidden();
    }

    public function test_a_user_cannot_run_commands_in_another_users_session(): void
    {
        $owner = $this->admin();
        $intruder = $this->admin();

        $ownerComponent = Livewire::actingAs($owner)->test(Terminal::class);
        $ownerComponent->call('openNewTab');
        $sessionId = TerminalSession::query()->first()->id;

        $intruderComponent = Livewire::actingAs($intruder)->test(Terminal::class);

        $this->expectException(HttpException::class);
        $intruderComponent->instance()->runCommand($sessionId, 'echo hijacked');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
