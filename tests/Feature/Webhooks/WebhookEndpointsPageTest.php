<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookEvent;
use App\Filament\Pages\Profile\WebhookEndpoints;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WebhookEndpointsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_a_webhook_endpoint(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(WebhookEndpoints::class)
            ->callTableAction('create', data: [
                'url' => 'https://example.test/hook',
                'events' => [WebhookEvent::DeploymentSucceeded->value],
            ]);

        $this->assertDatabaseHas('webhook_endpoints', ['user_id' => $user->id, 'url' => 'https://example.test/hook']);
    }

    public function test_a_user_only_sees_their_own_endpoints(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $other->webhookEndpoints()->create(['url' => 'https://other.test', 'secret' => 'x', 'events' => [WebhookEvent::DeploymentSucceeded->value]]);
        $mine = $user->webhookEndpoints()->create(['url' => 'https://mine.test', 'secret' => 'x', 'events' => [WebhookEvent::DeploymentSucceeded->value]]);

        Livewire::actingAs($user)
            ->test(WebhookEndpoints::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCountTableRecords(1);
    }

    public function test_a_user_can_toggle_and_delete_an_endpoint(): void
    {
        $user = User::factory()->create();
        $endpoint = $user->webhookEndpoints()->create([
            'url' => 'https://example.test/hook',
            'secret' => 'x',
            'events' => [WebhookEvent::DeploymentSucceeded->value],
        ]);

        Livewire::actingAs($user)
            ->test(WebhookEndpoints::class)
            ->callTableAction('toggle', $endpoint);

        $this->assertFalse($endpoint->fresh()->is_enabled);

        Livewire::actingAs($user)
            ->test(WebhookEndpoints::class)
            ->callTableAction('delete', $endpoint);

        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
    }

    public function test_the_secret_is_stored_encrypted(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(WebhookEndpoints::class)
            ->callTableAction('create', data: [
                'url' => 'https://example.test/hook',
                'events' => [WebhookEvent::DeploymentSucceeded->value],
            ]);

        $raw = DB::table('webhook_endpoints')->first();
        $endpoint = WebhookEndpoint::query()->first();

        $this->assertNotSame($endpoint->secret, $raw->secret);
    }
}
