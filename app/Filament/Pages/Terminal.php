<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Terminal\CloseTerminalSessionAction;
use App\Actions\Terminal\OpenTerminalSessionAction;
use App\Models\Server;
use App\Models\TerminalSession;
use App\Models\User;
use App\Services\Terminal\TerminalCommandService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

class Terminal extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.terminal';

    public ?int $activeServerId = null;

    /** @var list<int> */
    public array $openSessionIds = [];

    public ?int $activeSessionId = null;

    /**
     * Command a user typed that matched the dangerous-command guard, keyed
     * by session id, awaiting a "yes" reply before actually running - see
     * TerminalCommandService/DangerousCommandGuard.
     *
     * @var array<int, string>
     */
    public array $pendingConfirmations = [];

    public static function canAccess(): bool
    {
        $server = Server::query()->where('is_local', true)->first();

        return $server !== null && auth()->user()?->can('useTerminal', $server) === true;
    }

    public function mount(): void
    {
        $server = $this->server();

        abort_unless($server !== null && auth()->user()->can('useTerminal', $server), 403);

        $this->activeServerId = $server->id;
    }

    public function server(): ?Server
    {
        return Server::query()->where('is_local', true)->first();
    }

    /**
     * @return list<TerminalSession>
     */
    #[Computed]
    public function openSessions(): array
    {
        if ($this->openSessionIds === []) {
            return [];
        }

        return TerminalSession::query()
            ->whereIn('id', $this->openSessionIds)
            ->get()
            ->sortBy(fn (TerminalSession $session) => array_search($session->id, $this->openSessionIds, true))
            ->values()
            ->all();
    }

    public function openNewTab(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $server = $this->server();

        abort_unless($server !== null && $user->can('useTerminal', $server), 403);

        $session = app(OpenTerminalSessionAction::class)->handle($server, $user, 'Tab '.(count($this->openSessionIds) + 1));

        $this->openSessionIds[] = $session->id;
        $this->activeSessionId = $session->id;
    }

    public function switchTab(int $sessionId): void
    {
        if (in_array($sessionId, $this->openSessionIds, true)) {
            $this->activeSessionId = $sessionId;
        }
    }

    public function closeTab(int $sessionId): void
    {
        $session = TerminalSession::query()->find($sessionId);

        if ($session !== null && $session->user_id === auth()->id()) {
            app(CloseTerminalSessionAction::class)->handle($session, auth()->user());
        }

        $this->openSessionIds = array_values(array_filter($this->openSessionIds, fn (int $id): bool => $id !== $sessionId));
        unset($this->pendingConfirmations[$sessionId]);

        if ($this->activeSessionId === $sessionId) {
            $this->activeSessionId = $this->openSessionIds[0] ?? null;
        }
    }

    /**
     * @return array{output: string, prompt: string, requiresConfirmation: bool}
     */
    public function runCommand(int $sessionId, string $command): array
    {
        /** @var User $user */
        $user = auth()->user();
        $server = $this->server();

        abort_unless($server !== null && $user->can('useTerminal', $server), 403);

        $session = TerminalSession::query()->findOrFail($sessionId);
        abort_unless($session->user_id === $user->id, 403);

        $service = app(TerminalCommandService::class);

        if (isset($this->pendingConfirmations[$sessionId])) {
            $pendingCommand = $this->pendingConfirmations[$sessionId];
            unset($this->pendingConfirmations[$sessionId]);

            if (strtolower(trim($command)) === 'yes') {
                $result = $service->execute($session->fresh(), $user->id, $pendingCommand, confirmed: true);

                return [
                    'output' => $result->output,
                    'prompt' => $this->promptFor($session->fresh()),
                    'requiresConfirmation' => false,
                ];
            }

            // Anything other than "yes" cancels the pending command and the
            // newly typed line is treated as a fresh command below.
        }

        $result = $service->execute($session->fresh(), $user->id, $command);

        if ($result->requiresConfirmation) {
            $this->pendingConfirmations[$sessionId] = trim($command);

            return [
                'output' => $result->output."\r\nType \"yes\" and press Enter to run it anyway.",
                'prompt' => 'confirm? > ',
                'requiresConfirmation' => true,
            ];
        }

        return [
            'output' => $result->output,
            'prompt' => $this->promptFor($session->fresh()),
            'requiresConfirmation' => false,
        ];
    }

    public function promptFor(TerminalSession $session): string
    {
        return $session->current_directory.'> ';
    }
}
