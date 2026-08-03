<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_project_and_developer_info(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['sha' => 'anything'])]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/about');

        $response->assertSuccessful();
        $response->assertSee('MTP Deploy');
        $response->assertSee('manoranjan2050/mtp-deploy');
        $response->assertSee('Declaration');
    }
}
