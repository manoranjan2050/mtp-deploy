<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Terminal;

use App\Enums\TerminalCommandStatus;
use App\Models\Server;
use App\Models\TerminalCommand;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\Terminal\TerminalCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Exercises TerminalCommandService against a real spawned process (not
 * mocked) - same "real infrastructure over mocks" philosophy used throughout
 * this project (see GitDeploymentServiceTest, DatabaseManagerServiceTest).
 * `echo`/`exit` are used because both cmd.exe (Windows) and a POSIX shell
 * understand them identically, so these tests are meaningful on this dev
 * machine and on a real Linux server alike.
 */
class TerminalCommandServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir().'/mtp-terminal-test-'.uniqid();
        File::makeDirectory($this->workDir, recursive: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);

        parent::tearDown();
    }

    public function test_it_executes_a_real_command_and_captures_output(): void
    {
        $result = app(TerminalCommandService::class)->execute($this->openTerminalSession(), $this->userId(), 'echo hello-mtp');

        $this->assertSame(TerminalCommandStatus::Executed, $result->status);
        $this->assertStringContainsString('hello-mtp', $result->output);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_it_captures_a_non_zero_exit_code(): void
    {
        $result = app(TerminalCommandService::class)->execute($this->openTerminalSession(), $this->userId(), 'exit 3');

        $this->assertSame(3, $result->exitCode);
    }

    public function test_it_persists_a_history_row_per_command(): void
    {
        $session = $this->openTerminalSession();

        app(TerminalCommandService::class)->execute($session, $this->userId(), 'echo one');
        app(TerminalCommandService::class)->execute($session, $this->userId(), 'echo two');

        $this->assertSame(2, TerminalCommand::query()->where('terminal_session_id', $session->id)->count());
    }

    public function test_cd_into_a_real_subdirectory_changes_the_current_directory(): void
    {
        File::makeDirectory($this->workDir.'/sub');
        $session = $this->openTerminalSession();

        $result = app(TerminalCommandService::class)->execute($session, $this->userId(), 'cd sub');

        $this->assertSame(0, $result->exitCode);
        $this->assertSame(realpath($this->workDir.'/sub'), $result->currentDirectory);
        $this->assertSame(realpath($this->workDir.'/sub'), $session->fresh()->current_directory);
    }

    public function test_cd_into_a_nonexistent_directory_does_not_change_directory(): void
    {
        $session = $this->openTerminalSession();
        $originalDirectory = $session->current_directory;

        $result = app(TerminalCommandService::class)->execute($session, $this->userId(), 'cd nope-does-not-exist');

        $this->assertSame(1, $result->exitCode);
        $this->assertStringContainsString('no such directory', $result->output);
        $this->assertSame($originalDirectory, $session->fresh()->current_directory);
    }

    public function test_a_dangerous_command_is_blocked_without_confirmation(): void
    {
        $result = app(TerminalCommandService::class)->execute($this->openTerminalSession(), $this->userId(), 'DROP DATABASE production');

        $this->assertSame(TerminalCommandStatus::Blocked, $result->status);
        $this->assertTrue($result->requiresConfirmation);

        $this->assertSame(
            TerminalCommandStatus::Blocked,
            TerminalCommand::query()->latest('id')->first()->status
        );
    }

    public function test_a_dangerous_command_runs_when_confirmed(): void
    {
        // `DROP DATABASE production` matches the guard's pattern (it's SQL
        // text the guard flags for a shell-terminal context) but "DROP" is
        // not a real executable on any OS's PATH, so actually letting it run
        // here is inert - it fails with "command not found", not a real
        // database drop. This is deliberately never a genuinely destructive
        // shell primitive (`rm -rf`, `mkfs`, the fork bomb, ...) precisely so
        // this test can safely prove the confirmation bypass without risking
        // the test machine.
        $session = $this->openTerminalSession();

        $result = app(TerminalCommandService::class)->execute($session, $this->userId(), 'DROP DATABASE production', confirmed: true);

        $this->assertSame(TerminalCommandStatus::Executed, $result->status);
        $this->assertFalse($result->requiresConfirmation);
    }

    private function openTerminalSession(): TerminalSession
    {
        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);

        return TerminalSession::query()->create([
            'server_id' => $server->id,
            'user_id' => $this->userId(),
            'label' => 'Test',
            'current_directory' => $this->workDir,
        ]);
    }

    private function userId(): int
    {
        return User::factory()->create()->id;
    }
}
