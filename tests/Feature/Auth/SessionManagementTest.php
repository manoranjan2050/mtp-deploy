<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\RevokeSessionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoking_a_session_deletes_it(): void
    {
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-a',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('x'),
            'last_activity' => now()->timestamp,
        ]);

        app(RevokeSessionAction::class)->handle($user, 'session-a');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
    }

    public function test_revoking_a_session_cannot_affect_another_users_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-other',
            'user_id' => $otherUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('x'),
            'last_activity' => now()->timestamp,
        ]);

        app(RevokeSessionAction::class)->handle($user, 'session-other');

        $this->assertDatabaseHas('sessions', ['id' => 'session-other']);
    }

    public function test_revoke_others_keeps_current_session_and_removes_the_rest(): void
    {
        $user = User::factory()->create();

        foreach (['current', 'a', 'b'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('x'),
                'last_activity' => now()->timestamp,
            ]);
        }

        app(RevokeSessionAction::class)->handleOthers($user, 'current');

        $this->assertDatabaseHas('sessions', ['id' => 'current']);
        $this->assertDatabaseMissing('sessions', ['id' => 'a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'b']);
    }
}
