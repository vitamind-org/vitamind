<?php

namespace Tests\Feature;

use App\Models\User;
use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_workspaces_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/workspaces');

        $response->assertOk();
    }

    public function test_user_can_create_a_workspace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/workspaces', [
            'name' => 'acme-corp',
        ]);

        $response->assertRedirect(route('workspaces'));
        $this->assertDatabaseHas('workspaces', ['name' => 'acme-corp']);
        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_switch_active_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $user->ensureHasDefaultWorkspace();
        $other = Workspace::create(['name' => 'other-workspace']);
        $other->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->patch("/settings/workspaces/switch/{$other->id}");

        $response->assertRedirect();
        $this->assertSame($other->id, $user->fresh()->current_workspace_id);
        $this->assertNotEquals($workspace->id, $other->id);
    }

    public function test_user_cannot_switch_to_a_workspace_they_are_not_a_member_of(): void
    {
        $user = User::factory()->create();
        $other = Workspace::create(['name' => 'someone-elses-workspace']);

        $response = $this->actingAs($user)->patch("/settings/workspaces/switch/{$other->id}");

        $response->assertForbidden();
    }
}
