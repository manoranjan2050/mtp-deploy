<?php

declare(strict_types=1);

namespace Tests\Feature\Docker;

use App\Actions\Docker\PullImageAction;
use App\Actions\Docker\RemoveImageAction;
use App\Actions\Docker\RestartContainerAction;
use App\Actions\Docker\StartContainerAction;
use App\Actions\Docker\StopContainerAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DockerActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_start_action_logs_activity(): void
    {
        Http::fake(['*/containers/*/start' => Http::response('', 204)]);

        app(StartContainerAction::class)->handle('abc123');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'docker', 'description' => 'started container']);
    }

    public function test_stop_action_logs_activity(): void
    {
        Http::fake(['*/containers/*/stop' => Http::response('', 204)]);

        app(StopContainerAction::class)->handle('abc123');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'docker', 'description' => 'stopped container']);
    }

    public function test_restart_action_logs_activity(): void
    {
        Http::fake(['*/containers/*/restart' => Http::response('', 204)]);

        app(RestartContainerAction::class)->handle('abc123');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'docker', 'description' => 'restarted container']);
    }

    public function test_pull_image_action_logs_activity(): void
    {
        Http::fake(['*/images/create*' => Http::response('', 200)]);

        app(PullImageAction::class)->handle('nginx:latest');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'docker', 'description' => 'pulled image']);
    }

    public function test_remove_image_action_logs_activity(): void
    {
        Http::fake(['*/images/*' => Http::response('', 204)]);

        app(RemoveImageAction::class)->handle('sha256:xyz');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'docker', 'description' => 'removed image']);
    }
}
