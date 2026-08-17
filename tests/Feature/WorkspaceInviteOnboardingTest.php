<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

class WorkspaceInviteOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_invitation_link_404s_after_deletion(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-a']);
        $invite = $workspace->users()->create(['email' => 'revoke-me@example.com', 'role' => 'user']);

        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);

        $this->actingAs($owner)
            ->delete("/settings/workspaces/{$workspace->id}/users/{$invite->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('user_workspace', ['id' => $invite->id]);

        $this->get($signedUrl)->assertNotFound();
    }

    public function test_registering_via_a_specific_invite_link_accepts_only_that_invitation(): void
    {
        $owner = User::factory()->create();
        $workspaceA = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-a']);
        $workspaceB = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-b']);

        $inviteA = $workspaceA->users()->create(['email' => 'invitee@example.com', 'role' => 'user']);
        $inviteB = $workspaceB->users()->create(['email' => 'invitee@example.com', 'role' => 'user']);

        $signedUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspaceA->id,
            'invite' => $inviteA->id,
        ]);

        $this->get($signedUrl)->assertRedirect(route('register'));
        $this->assertSame($inviteA->id, session('pending_invite_id'));

        $this->withSession(['pending_invite_id' => $inviteA->id])->post('/register', [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'invitee@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_workspace', [
            'id' => $inviteA->id,
            'user_id' => $user->id,
            'email' => null,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('user_workspace', [
            'id' => $inviteB->id,
            'user_id' => null,
            'email' => 'invitee@example.com',
        ]);
        $this->assertSame($workspaceA->id, $user->fresh()->current_workspace_id);
    }

    public function test_registering_without_opening_invite_link_does_not_auto_accept(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-a']);
        $invite = $workspace->users()->create(['email' => 'invitee2@example.com', 'role' => 'user']);

        $this->post('/register', [
            'name' => 'Invitee Two',
            'email' => 'invitee2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'invitee2@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_workspace', [
            'id' => $invite->id,
            'user_id' => null,
            'email' => 'invitee2@example.com',
        ]);
        $this->assertNull($user->fresh()->current_workspace_id);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('workspaces.onboarding'));
    }

    public function test_register_page_shows_pending_invite_context_when_session_has_it(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-a']);
        $invite = $workspace->users()->create(['email' => 'invitee3@example.com', 'role' => 'user']);

        $response = $this->withSession(['pending_invite_id' => $invite->id])->get('/register');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('pendingInvite.email', 'invitee3@example.com')
            ->where('pendingInvite.workspaceName', 'workspace-a')
        );
    }

    public function test_registering_with_a_different_email_than_the_invite_shows_a_mismatch_message_once(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-a']);
        $invite = $workspace->users()->create(['email' => 'invited@example.com', 'role' => 'user']);

        $this->withSession(['pending_invite_id' => $invite->id])->post('/register', [
            'name' => 'Different Email',
            'email' => 'different@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'different@example.com')->firstOrFail();

        // The invite was never touched — no auto-accept on email mismatch.
        $this->assertDatabaseHas('user_workspace', [
            'id' => $invite->id,
            'user_id' => null,
            'email' => 'invited@example.com',
        ]);
        $this->assertNull($user->fresh()->current_workspace_id);

        $mismatch = session('invite_email_mismatch');
        $this->assertSame('invited@example.com', $mismatch['email'] ?? null);
        $this->assertSame('workspace-a', $mismatch['workspace_name'] ?? null);

        $response = $this->actingAs($user)
            ->withSession(['invite_email_mismatch' => $mismatch])
            ->get(route('workspaces.onboarding'));

        $response->assertInertia(fn ($page) => $page
            ->where('emailMismatch.email', 'invited@example.com')
            ->where('emailMismatch.workspace_name', 'workspace-a')
        );

        // Shown once — consumed and cleared from session after that render.
        $this->assertNull(session('invite_email_mismatch'));
    }

    public function test_registering_with_no_invitations_routes_to_onboarding_with_suggested_name(): void
    {
        $this->post('/register', [
            'name' => 'Solo Signup',
            'email' => 'solo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'solo@example.com')->firstOrFail();
        $this->assertNull($user->current_workspace_id);

        $response = $this->actingAs($user)->get(route('workspaces.onboarding'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            // shouldExist=false: Inertia's testing helper only looks under
            // resources/js/pages/ for on-disk verification, which doesn't
            // know about the @plugin/{name} alias (dev-packages/vitamind-
            // workspace-plugin/resources/js/pages/onboarding.tsx, resolved
            // via vite.config.ts's getPluginAliases()).
            ->component('@plugin/workspace-plugin/onboarding', false)
            ->where('invitations', [])
            ->where('suggestedWorkspaceName', 'Solo Signup Workspace')
        );
    }

    public function test_onboarding_suggested_name_preserves_case_and_strips_invalid_characters(): void
    {
        $user = User::factory()->create(['name' => "Zoë O'Brien"]);

        $response = $this->actingAs($user)->get(route('workspaces.onboarding'));

        $response->assertInertia(fn ($page) => $page->where('suggestedWorkspaceName', 'Zoe OBrien Workspace'));
    }

    public function test_accepting_from_onboarding_screen_attaches_only_the_chosen_invitation(): void
    {
        $inviter = User::factory()->create();
        $workspaceA = app(CreateWorkspace::class)->create($inviter, ['name' => 'workspace-a']);
        $workspaceB = app(CreateWorkspace::class)->create($inviter, ['name' => 'workspace-b']);

        $user = User::factory()->create();
        $inviteA = $workspaceA->users()->create(['email' => $user->email, 'role' => 'user']);
        $inviteB = $workspaceB->users()->create(['email' => $user->email, 'role' => 'user']);

        $response = $this->actingAs($user)->post("/settings/workspaces/onboarding/{$inviteA->id}/accept");

        $response->assertRedirect(route('workspaces'));

        $this->assertDatabaseHas('user_workspace', [
            'id' => $inviteA->id,
            'user_id' => $user->id,
            'email' => null,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('user_workspace', [
            'id' => $inviteB->id,
            'user_id' => null,
            'email' => $user->email,
        ]);
        $this->assertSame($workspaceA->id, $user->fresh()->current_workspace_id);
    }

    public function test_unauthenticated_visitor_can_open_valid_link_but_expired_or_tampered_links_are_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'workspace-a']);
        $invite = $workspace->users()->create(['email' => 'guest@example.com', 'role' => 'user']);
        // A second, real invitation to swap in — tampering by substituting a
        // valid-looking but different identifier, not a nonexistent one,
        // exercises the signature check itself rather than a 404 on binding.
        $otherInvite = $workspace->users()->create(['email' => 'someone-else@example.com', 'role' => 'user']);

        $validUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->addDays(7), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);
        $this->get($validUrl)->assertRedirect(route('register'));

        $expiredUrl = URL::temporarySignedRoute('workspaces.invitations.accept', now()->subDay(), [
            'workspace' => $workspace->id,
            'invite' => $invite->id,
        ]);
        $this->get($expiredUrl)->assertForbidden();

        $tamperedUrl = preg_replace('/\/\d+\/accept/', "/{$otherInvite->id}/accept", $validUrl);
        $this->get($tamperedUrl)->assertForbidden();
    }

    /**
     * At the plugin level, an already-active workspace member accepting a
     * second invite via a signed link works fine (the plugin itself defaults
     * to multi-workspace-per-user). In this app, though, WakuWaku's own
     * `App\Http\Middleware\PreventMultipleWorkspaces` (registered in
     * `bootstrap/app.php`, not in the plugin) enforces single-workspace
     * tenancy on top of it — see `tests/Feature/SingleWorkspaceTenancyTest.php`
     * for the app-level coverage of that gate. This test now asserts the
     * gated (403) outcome so it reflects this app's actual end-to-end
     * behavior rather than the plugin's own permissive default.
     */
    public function test_authenticated_user_with_active_workspace_is_blocked_from_accepting_another_invite_via_signed_link(): void
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
        $this->assertNotSame($otherWorkspace->id, $user->fresh()->current_workspace_id);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_is_default_membership_tracking_and_reassignment(): void
    {
        $user = User::factory()->create();

        $workspaceA = app(CreateWorkspace::class)->create($user, ['name' => 'workspace-a']);
        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspaceA->id,
            'is_default' => true,
        ]);

        $workspaceB = app(CreateWorkspace::class)->create($user, ['name' => 'workspace-b']);
        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspaceB->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspaceA->id,
            'is_default' => false,
        ]);

        $workspaceC = Workspace::create(['name' => 'workspace-c']);
        $membershipC = $workspaceC->users()->create(['user_id' => $user->id, 'role' => 'user']);

        $this->actingAs($user)
            ->delete("/settings/workspaces/{$workspaceB->id}/leave")
            ->assertRedirect();

        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspaceA->id,
            'is_default' => true,
        ]);

        $this->expectException(QueryException::class);
        UserWorkspace::query()->where('id', $membershipC->id)->update(['is_default' => true]);
    }
}
