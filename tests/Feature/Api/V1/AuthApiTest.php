<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_current_user(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        Sanctum::actingAs($user, ['profile:read']);

        $this->getJson('/api/v1/user')
            ->assertSuccessful()
            ->assertJsonPath('name', 'Ada Lovelace');
    }

    public function test_it_lists_the_users_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('Existing token', ['profile:read']);
        Sanctum::actingAs($user, ['profile:read']);

        $response = $this->getJson('/api/v1/auth/tokens');

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    }

    public function test_it_issues_a_new_token_after_password_reauth(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        Sanctum::actingAs($user, ['profile:read']);

        $response = $this->postJson('/api/v1/auth/tokens', [
            'name' => 'CI token',
            'password' => 'correct-password',
            'abilities' => ['websites:read'],
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_it_rejects_token_creation_with_the_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        Sanctum::actingAs($user, ['profile:read']);

        $this->postJson('/api/v1/auth/tokens', [
            'name' => 'CI token',
            'password' => 'wrong-password',
            'abilities' => ['websites:read'],
        ])->assertForbidden();
    }

    public function test_it_revokes_a_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Revoke me', ['profile:read']);
        Sanctum::actingAs($user, ['profile:read']);

        $this->deleteJson("/api/v1/auth/tokens/{$token->accessToken->id}")->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_it_lists_active_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'test-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('test'),
            'last_activity' => now()->timestamp,
        ]);
        Sanctum::actingAs($user, ['sessions:write']);

        $response = $this->getJson('/api/v1/user/sessions');

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    }

    public function test_it_revokes_a_session(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'revoke-me',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('test'),
            'last_activity' => now()->timestamp,
        ]);
        Sanctum::actingAs($user, ['sessions:write']);

        $this->deleteJson('/api/v1/user/sessions/revoke-me')->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['id' => 'revoke-me']);
    }
}
