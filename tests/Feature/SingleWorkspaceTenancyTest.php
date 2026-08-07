<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

class SingleWorkspaceTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_routed_to_onboarding_instead_of_getting_a_workspace_automatically(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('workspaces.onboarding'));
        $this->assertNull($user->fresh()->current_workspace_id);
        $this->assertDatabaseMissing('user_workspace', ['user_id' => $user->id]);
    }

    public function test_user_with_existing_workspace_cannot_create_another_workspace(): void
    {
        $user = User::factory()->create();
        app(CreateWorkspace::class)->create($user, ['name' => 'first-workspace']);

        $response = $this->actingAs($user)->post('/settings/workspaces', ['name' => 'second-workspace']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('workspaces', ['name' => 'second-workspace']);
        $this->assertSame(1, $user->allWorkspaces()->count());
    }

    public function test_user_without_a_workspace_can_create_one_via_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/workspaces', ['name' => 'first-workspace']);

        $response->assertRedirect(route('workspaces'));
        $this->assertDatabaseHas('workspaces', ['name' => 'first-workspace']);
    }

    public function test_user_with_existing_workspace_cannot_accept_an_invitation_via_signed_link(): void
    {
        $user = User::factory()->create();
        app(CreateWorkspace::class)->create($user, ['name' => 'own-workspace']);

        $inviter = User::factory()->create();
        $otherWorkspace = app(CreateWorkspace::class)->create($inviter, ['name' => 'other-workspace']);
        $invite = $otherWorkspace->users()->create(['email' => $user->email, 'role' => 'user']);

        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $otherWorkspace->id,
            'invite' => $invite->id,
        ]);

        $response = $this->actingAs($user)->get($signedUrl);

        $response->assertForbidden();
        $this->assertSame(1, $user->allWorkspaces()->count());
        $this->assertDatabaseHas('user_workspace', [
            'id' => $invite->id,
            'user_id' => null,
            'email' => $user->email,
        ]);
    }

    public function test_user_with_existing_workspace_cannot_accept_an_invitation_via_onboarding_screen(): void
    {
        $user = User::factory()->create();
        app(CreateWorkspace::class)->create($user, ['name' => 'own-workspace']);

        $inviter = User::factory()->create();
        $otherWorkspace = app(CreateWorkspace::class)->create($inviter, ['name' => 'other-workspace']);
        $invite = $otherWorkspace->users()->create(['email' => $user->email, 'role' => 'user']);

        $response = $this->actingAs($user)->post("/settings/workspaces/onboarding/{$invite->id}/accept");

        $response->assertForbidden();
        $this->assertSame(1, $user->allWorkspaces()->count());
    }

    public function test_user_without_a_workspace_can_accept_an_invitation_via_onboarding_screen(): void
    {
        $user = User::factory()->create();

        $inviter = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($inviter, ['name' => 'inviting-workspace']);
        $invite = $workspace->users()->create(['email' => $user->email, 'role' => 'user']);

        $response = $this->actingAs($user)->post("/settings/workspaces/onboarding/{$invite->id}/accept");

        $response->assertRedirect(route('workspaces'));
        $this->assertSame($workspace->id, $user->fresh()->current_workspace_id);
    }

    public function test_workspace_plugin_itself_still_allows_multiple_workspaces_when_gate_is_not_active(): void
    {
        // Sanity check for the D10 constraint (single-workspace-tenancy lives
        // entirely in WakuWaku's app layer): the plugin's own model-level
        // relations impose no such limit on their own — a workspace can gain
        // a second member row directly, independent of the HTTP-layer gate
        // this test suite otherwise exercises.
        $user = User::factory()->create();
        app(CreateWorkspace::class)->create($user, ['name' => 'workspace-a']);

        $second = Workspace::create(['name' => 'workspace-b']);
        $second->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        $this->assertSame(2, $user->allWorkspaces()->count());
    }
}
