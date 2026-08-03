<?php

declare(strict_types=1);

namespace App\Actions\Terminal;

use App\Models\TerminalSession;
use App\Models\User;

class CloseTerminalSessionAction
{
    public function handle(TerminalSession $session, User $user): void
    {
        $session->update(['closed_at' => now()]);

        activity('terminal')
            ->causedBy($user)
            ->performedOn($session->server)
            ->withProperties(['terminal_session_id' => $session->id])
            ->log('closed terminal session');
    }
}
