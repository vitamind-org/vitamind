<?php

namespace Tests\Feature;

use App\Models\User;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;
use VitaminD\Plugins\Workspace\Http\Resources\WorkspaceUserResource;
use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_workspaces_page(): void
    {
        $user = User::factory()->create();
        app(CreateWorkspace::class)->create($user, ['name' => 'acme-corp']);

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
            'is_default' => true,
        ]);
    }

    public function test_user_can_switch_active_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($user, ['name' => 'first-workspace']);
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
        app(CreateWorkspace::class)->create($user, ['name' => 'first-workspace']);
        $other = Workspace::create(['name' => 'someone-elses-workspace']);

        $response = $this->actingAs($user)->patch("/settings/workspaces/switch/{$other->id}");

        $response->assertForbidden();
    }

    public function test_workspace_name_allows_capital_letters_and_spaces(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/workspaces', [
            'name' => 'Acme Corp',
        ]);

        $response->assertRedirect(route('workspaces'));
        $this->assertDatabaseHas('workspaces', [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);
    }

    public function test_workspace_name_rejects_disallowed_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/workspaces', [
            'name' => 'Acme! Corp',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('workspaces', ['name' => 'Acme! Corp']);
    }

    public function test_workspace_name_rejects_input_that_normalizes_to_an_empty_slug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/settings/workspaces', [
            'name' => '--- ---',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_workspace_names_differing_only_by_case_collide(): void
    {
        $owner = User::factory()->create();
        app(CreateWorkspace::class)->create($owner, ['name' => 'Acme Corp']);

        $other = User::factory()->create();
        $response = $this->actingAs($other)->post('/settings/workspaces', [
            'name' => 'ACME CORP',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Workspace::where('slug', 'acme-corp')->count());
    }

    public function test_workspace_names_normalizing_to_different_slugs_both_succeed(): void
    {
        $owner = User::factory()->create();
        app(CreateWorkspace::class)->create($owner, ['name' => 'Acme Corp']);

        $other = User::factory()->create();
        $response = $this->actingAs($other)->post('/settings/workspaces', [
            'name' => 'Acme Corp Two',
        ]);

        $response->assertRedirect(route('workspaces'));
        $this->assertDatabaseHas('workspaces', ['name' => 'Acme Corp Two', 'slug' => 'acme-corp-two']);
    }

    public function test_user_can_rename_a_workspace_with_capital_letters_and_spaces(): void
    {
        $user = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($user, ['name' => 'first-workspace']);

        $response = $this->actingAs($user)->patch("/settings/workspaces/{$workspace->id}", [
            'name' => 'Renamed Workspace',
        ]);

        $response->assertRedirect(route('workspaces'));
        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Renamed Workspace',
            'slug' => 'renamed-workspace',
        ]);
    }

    public function test_renaming_a_workspace_to_a_slug_used_by_another_workspace_fails(): void
    {
        $user = User::factory()->create();
        $workspaceA = app(CreateWorkspace::class)->create($user, ['name' => 'workspace-a']);
        app(CreateWorkspace::class)->create($user, ['name' => 'workspace-b']);

        $response = $this->actingAs($user)->patch("/settings/workspaces/{$workspaceA->id}", [
            'name' => 'WORKSPACE-B',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_workspace_user_resource_marks_registered_vs_invited_type(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp']);
        $pendingInvite = $workspace->users()->create(['email' => 'pending@example.com', 'role' => 'user']);
        $ownerMembership = $workspace->users()->where('user_id', $owner->id)->firstOrFail();

        $this->assertSame('user', (new WorkspaceUserResource($ownerMembership))->toArray(request())['type']);
        $this->assertSame('invitation', (new WorkspaceUserResource($pendingInvite))->toArray(request())['type']);
    }
}
