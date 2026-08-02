<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\BootstrapRegister;
use App\Listeners\Auth\AssignSuperAdminRoleOnFirstRegistration;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_first_user_can_register_and_becomes_super_admin(): void
    {
        Livewire::test(BootstrapRegister::class)
            ->fillForm([
                'name' => 'First User',
                'email' => 'first@example.com',
                'password' => 'password123',
                'passwordConfirmation' => 'password123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'first@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('super-admin'));
    }

    public function test_registration_page_redirects_to_login_once_a_user_exists(): void
    {
        User::factory()->create();

        $response = $this->get('/admin/register');

        $response->assertRedirect();
        $this->assertStringContainsString('/admin/login', $response->headers->get('Location'));
    }

    public function test_registration_listener_does_not_grant_super_admin_if_one_already_exists(): void
    {
        // Exercises the listener directly (not through the page), since the
        // page-level redirect guard is a separate layer covered by the test
        // above - this proves the second, independent guard also holds if
        // the page guard is ever bypassed (see docs/Security.md).
        User::factory()->create()->assignRole('super-admin');

        $secondUser = User::factory()->create();

        (new AssignSuperAdminRoleOnFirstRegistration)->handle(new Registered($secondUser));

        $this->assertFalse($secondUser->fresh()->hasRole('super-admin'));
    }
}
