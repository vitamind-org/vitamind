<?php

namespace Tests\Feature;

use App\Models\User;
use VitaminD\Core\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginEnableDisableTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_disable_an_enabled_plugin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plugin = Plugin::where('folder', 'HelloWorld')->firstOrFail();
        $this->assertTrue($plugin->is_enabled);

        $response = $this->actingAs($admin)->patch('/admin/plugins/disable', ['id' => $plugin->id]);

        $response->assertRedirect();
        $this->assertFalse($plugin->refresh()->is_enabled);
    }

    public function test_admin_can_enable_a_disabled_plugin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $plugin = Plugin::where('folder', 'HelloWorld')->firstOrFail();
        $plugin->update(['is_enabled' => false]);

        $response = $this->actingAs($admin)->patch('/admin/plugins/enable', ['id' => $plugin->id]);

        $response->assertRedirect();
        $this->assertTrue($plugin->refresh()->is_enabled);
    }

    public function test_non_admin_cannot_enable_or_disable_plugins(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $plugin = Plugin::where('folder', 'HelloWorld')->firstOrFail();

        $response = $this->actingAs($user)->patch('/admin/plugins/disable', ['id' => $plugin->id]);

        $response->assertStatus(404);
        $this->assertTrue($plugin->refresh()->is_enabled);
    }
}
