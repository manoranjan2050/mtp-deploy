<?php

declare(strict_types=1);

namespace App\Actions\Terminal;

use App\Models\Server;
use App\Models\TerminalSession;
use App\Models\User;

class OpenTerminalSessionAction
{
    public function handle(Server $server, User $user, ?string $label = null): TerminalSession
    {
        $session = TerminalSession::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'label' => $label ?? 'Terminal',
            'current_directory' => (string) config('mtp.terminal_default_directory'),
        ]);

        activity('terminal')
            ->causedBy($user)
            ->performedOn($server)
            ->withProperties(['terminal_session_id' => $session->id])
            ->log('opened terminal session');

        return $session;
    }
}
