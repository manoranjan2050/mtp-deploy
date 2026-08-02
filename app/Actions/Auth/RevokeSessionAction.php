<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeSessionAction
{
    public function handle(User $user, string $sessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['session_id' => $sessionId])
            ->log('revoked a session');
    }

    public function handleOthers(User $user, string $currentSessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        activity('auth')
            ->causedBy($user)
            ->log('revoked all other sessions');
    }
}
