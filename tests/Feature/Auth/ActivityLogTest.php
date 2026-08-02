<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\CreateApiTokenAction;
use App\Enums\ApiTokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_writes_an_activity_log_entry(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            Activity::query()
                ->where('subject_type', User::class)
                ->where('subject_id', $user->id)
                ->where('event', 'created')
                ->exists()
        );
    }

    public function test_updating_a_tracked_user_field_writes_an_activity_log_entry(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $user->update(['is_active' => false]);

        $entry = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(false, $entry->properties['attributes']['is_active']);
    }

    public function test_creating_an_api_token_writes_an_activity_log_entry(): void
    {
        $user = User::factory()->create();

        app(CreateApiTokenAction::class)->handle($user, 'Logged Token', [ApiTokenAbility::FullAccess->value]);

        $this->assertTrue(
            Activity::query()
                ->where('causer_id', $user->id)
                ->where('description', 'created an API token')
                ->exists()
        );
    }
}
