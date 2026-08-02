<?php

declare(strict_types=1);

namespace Tests\Feature\Websites;

use App\Enums\WebsiteFramework;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders the real Websites list page with an actual row present - this is
 * what catches bugs in row-action closures (icon/label/color callbacks),
 * which are only evaluated when Filament actually renders a record, not when
 * submitting a form. A real bug slipped past every other test here: the
 * "suspend" action's ->icon() closure was typed to return `string` but
 * actually returned a `Heroicon` enum instance, throwing a TypeError only
 * once the list page rendered a row to apply it to.
 */
class ListWebsitesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_websites_list_page_renders_with_a_real_row(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $server = Server::query()->create(['name' => 'Test Server', 'is_local' => true]);
        Website::query()->create([
            'server_id' => $server->id,
            'name' => 'Example',
            'domain' => 'example.test',
            'document_root' => '/var/www/example.test',
            'php_version' => '8.3',
            'framework' => WebsiteFramework::Laravel,
        ]);

        $response = $this->actingAs($admin)->get('/admin/websites');

        $response->assertSuccessful();
        $response->assertSee('example.test');
    }
}
