<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use VitaminD\Core\Actions\Role\AssignRole;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;
use VitaminD\Plugins\Workspace\Actions\Workspaces\InviteToWorkspace;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\PluginSdk\RegisterRole;

/**
 * Covers the workspace invitation/role-assignment flows introduced by the
 * multi-role-authorization mechanism: an app-registered role assigned
 * scoped to a workspace on acceptance, the separate "grant system Admin"
 * branch, the optional "no role at all" branch, ownership as plain data
 * (not a role) and its removal protection, and role cleanup on
 * removal/leave. See `WorkspaceInviteOnboardingTest` for the pre-existing
 * invite/onboarding link-lifecycle coverage this complements.
 */
class WorkspaceRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function registerTestRole(string $shortKey = 'tester'): string
    {
        RegisterRole::make($shortKey)->title(ucfirst($shortKey))->register('test-plugin');

        return collect(RegisterRole::get())->keys()->first(fn (string $key) => str_ends_with($key, ".{$shortKey}"));
    }

    public function test_inviting_with_a_registered_role_assigns_it_scoped_to_workspace_on_acceptance(): void
    {
        $roleKey = $this->registerTestRole();

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $this->actingAs($owner)->post("/settings/workspaces/{$workspace->id}/users", [
            'email' => 'member@example.com',
            'role' => $roleKey,
        ])->assertRedirect();

        $invite = UserWorkspace::query()->where('email', 'member@example.com')->firstOrFail();
        $this->assertSame($roleKey, $invite->invited_role);
        $this->assertFalse($invite->is_admin_grant);

        $invitee = User::factory()->create(['email' => 'member@example.com']);
        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);

        $this->actingAs($invitee)->get($signedUrl)->assertRedirect(route('workspaces'));

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $invitee->id,
            'role' => $roleKey,
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
        ]);
        $this->assertFalse($invitee->fresh()->is_admin);
    }

    public function test_inviting_with_admin_grants_is_admin_without_a_workspace_role(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $this->actingAs($owner)->post("/settings/workspaces/{$workspace->id}/users", [
            'email' => 'admin-grant@example.com',
            'role' => InviteToWorkspace::ADMIN_OPTION,
        ])->assertRedirect();

        $invite = UserWorkspace::query()->where('email', 'admin-grant@example.com')->firstOrFail();
        $this->assertTrue($invite->is_admin_grant);
        $this->assertNull($invite->invited_role);

        $invitee = User::factory()->create(['email' => 'admin-grant@example.com']);
        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);

        $this->actingAs($invitee)->get($signedUrl)->assertRedirect(route('workspaces'));

        $this->assertTrue($invitee->fresh()->is_admin);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $invitee->id,
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
        ]);
    }

    public function test_inviting_with_no_role_or_admin_selected_grants_plain_membership(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $this->actingAs($owner)->post("/settings/workspaces/{$workspace->id}/users", [
            'email' => 'plain@example.com',
            'role' => InviteToWorkspace::NONE_OPTION,
        ])->assertRedirect();

        $invite = UserWorkspace::query()->where('email', 'plain@example.com')->firstOrFail();
        $this->assertFalse($invite->is_admin_grant);
        $this->assertNull($invite->invited_role);

        $invitee = User::factory()->create(['email' => 'plain@example.com']);
        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);

        $this->actingAs($invitee)->get($signedUrl)->assertRedirect(route('workspaces'));

        $this->assertDatabaseHas('user_workspace', ['id' => $invite->id, 'user_id' => $invitee->id]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $invitee->id,
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
        ]);
        $this->assertFalse($invitee->fresh()->is_admin);
    }

    public function test_an_invitation_cannot_grant_ownership(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        // There is no registered role or sentinel representing ownership —
        // an arbitrary string that isn't Admin, "none", or a real registry
        // key is simply an invalid selection.
        $this->actingAs($owner)->post("/settings/workspaces/{$workspace->id}/users", [
            'email' => 'wannabe-owner@example.com',
            'role' => 'owner',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('user_workspace', ['email' => 'wannabe-owner@example.com']);
    }

    public function test_a_non_owner_member_cannot_manage_the_workspace(): void
    {
        $roleKey = $this->registerTestRole('gudang');

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $member = User::factory()->create();
        $membership = $workspace->users()->create(['user_id' => $member->id]);
        app(AssignRole::class)->assign($member, $roleKey, 'workspace', $workspace->id);

        // Holding a registered role does not grant workspace-management
        // capability — only the owner (or a global is_admin) can invite,
        // remove, update, or delete the workspace itself.
        $this->actingAs($member)
            ->post("/settings/workspaces/{$workspace->id}/users", ['email' => 'x@example.com', 'role' => InviteToWorkspace::NONE_OPTION])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete("/settings/workspaces/{$workspace->id}/users/{$membership->id}")
            ->assertForbidden();
    }

    public function test_the_workspace_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $ownerMembership = $workspace->users()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->delete("/settings/workspaces/{$workspace->id}/users/{$ownerMembership->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('user_workspace', ['id' => $ownerMembership->id, 'user_id' => $owner->id]);
        $this->assertSame($owner->id, $workspace->fresh()->owner_id);
    }

    public function test_removing_a_member_clears_their_workspace_scoped_role_assignment(): void
    {
        $roleKey = $this->registerTestRole('member-role');

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $member = User::factory()->create();
        $membership = $workspace->users()->create(['user_id' => $member->id]);
        app(AssignRole::class)->assign($member, $roleKey, 'workspace', $workspace->id);

        $this->actingAs($owner)
            ->delete("/settings/workspaces/{$workspace->id}/users/{$membership->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('user_workspace', ['id' => $membership->id]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $member->id,
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
        ]);
    }

    public function test_leaving_a_workspace_clears_the_leaving_users_role_assignment(): void
    {
        $roleKey = $this->registerTestRole('leaver-role');

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        $member = User::factory()->create();
        $workspace->users()->create(['user_id' => $member->id]);
        app(AssignRole::class)->assign($member, $roleKey, 'workspace', $workspace->id);

        $this->actingAs($member)
            ->delete("/settings/workspaces/{$workspace->id}/leave")
            ->assertRedirect();

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $member->id,
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
        ]);
    }

    public function test_an_app_registered_role_is_selectable_on_invite_and_assignable_on_acceptance(): void
    {
        $salesKey = $this->registerTestRole('sales');

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);

        // The registry-driven shared prop the invite form's dropdown renders
        // from (see WorkspaceServiceProvider::invitableRolesSharedData()).
        $this->actingAs($owner)->get('/settings/workspaces')->assertInertia(
            fn ($page) => $page->where(
                'workspaceRoles',
                fn ($roles) => collect($roles)->pluck('key')->contains($salesKey)
            )
        );

        $this->actingAs($owner)->post("/settings/workspaces/{$workspace->id}/users", [
            'email' => 'sales@example.com',
            'role' => $salesKey,
        ])->assertRedirect();

        $invitee = User::factory()->create(['email' => 'sales@example.com']);
        $invite = UserWorkspace::query()->where('email', 'sales@example.com')->firstOrFail();
        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);

        $this->actingAs($invitee)->get($signedUrl)->assertRedirect(route('workspaces'));

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $invitee->id,
            'role' => $salesKey,
            'scope_type' => 'workspace',
            'scope_id' => $workspace->id,
        ]);
    }
}
