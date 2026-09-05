<?php

namespace VitaminD\Plugins\Workspace\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Models\Workspace;

class WorkspaceMenuRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme-'.Str::random(8)]);
        $workspace->owner_id = $user->id;
        $workspace->save();
        $workspace->users()->create(['user_id' => $user->id, 'is_default' => true]);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }

    public function test_workspaces_registers_a_settings_second_nav_entry_in_the_plugin_pages_prop(): void
    {
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->get('/settings/profile');

        $response->assertInertia(fn ($page) => $page
            ->where('pluginPages', fn ($pages) => $pages->contains(
                fn ($p) => $p['key'] === 'workspaces'
                    && $p['title'] === 'Workspaces'
                    && $p['href'] === route('workspaces')
                    && $p['placement'] === 'settings'
                    && $p['admin_only'] === false
            ))
        );
    }
}
