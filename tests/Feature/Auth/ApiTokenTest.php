<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\CreateApiTokenAction;
use App\Enums\ApiTokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_token_grants_only_the_selected_abilities(): void
    {
        $user = User::factory()->create();

        $token = app(CreateApiTokenAction::class)->handle(
            $user,
            'CI Token',
            [ApiTokenAbility::ViewProfile->value]
        );

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertSame(['profile:read'], $token->accessToken->abilities);
        $this->assertCount(1, $user->tokens);
    }

    public function test_a_token_without_an_ability_cannot_use_it(): void
    {
        $user = User::factory()->create();

        $token = app(CreateApiTokenAction::class)->handle(
            $user,
            'Limited Token',
            [ApiTokenAbility::ViewProfile->value]
        );

        $this->assertTrue($token->accessToken->can(ApiTokenAbility::ViewProfile->value));
        $this->assertFalse($token->accessToken->can(ApiTokenAbility::ManageSessions->value));
    }

    public function test_revoking_a_token_deletes_it(): void
    {
        $user = User::factory()->create();

        $token = app(CreateApiTokenAction::class)->handle($user, 'To revoke', [ApiTokenAbility::FullAccess->value]);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);

        $token->accessToken->delete();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
