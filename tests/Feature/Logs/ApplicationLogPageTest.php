<?php

declare(strict_types=1);

namespace Tests\Feature\Logs;

use App\Filament\Pages\ApplicationLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationLogPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        File::ensureDirectoryExists(storage_path('logs'));
        File::put(storage_path('logs/laravel.log'), "[2026-08-03] local.ERROR: boom\n");
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('logs/laravel.log'));

        parent::tearDown();
    }

    public function test_an_admin_can_view_the_application_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(ApplicationLog::class)
            ->assertSuccessful()
            ->assertSee('boom');
    }

    public function test_a_developer_cannot_access_the_application_log(): void
    {
        $developer = User::factory()->create();
        $developer->assignRole('developer');

        $this->actingAs($developer);
        $this->assertFalse(ApplicationLog::canAccess());

        Livewire::actingAs($developer)
            ->test(ApplicationLog::class)
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_access_the_application_log(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ApplicationLog::class)
            ->assertForbidden();
    }
}
