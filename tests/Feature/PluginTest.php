<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_plugins_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/plugins');

        $response->assertStatus(404);
    }

    public function test_admin_can_access_plugins_page(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/plugins');

        $response->assertOk();
    }

    public function test_local_plugins_are_discovered_automatically(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/plugins');

        $response->assertOk();

        $this->assertTrue(Plugin::where('folder', 'Acme/HelloWorld')->exists());
    }
}
