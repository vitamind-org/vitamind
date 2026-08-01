<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use VitaminD\Core\Actions\User\DeleteUser;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;

class WorkspaceUserCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_user_removes_their_workspace_membership(): void
    {
        $user = User::factory()->create();
        $workspace = $user->ensureHasDefaultWorkspace();

        $this->assertDatabaseHas('user_workspace', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        app(DeleteUser::class)->delete($user);

        $this->assertDatabaseMissing('user_workspace', ['user_id' => $user->id]);
    }

    public function test_deleting_a_user_does_not_delete_the_workspace_itself(): void
    {
        $user = User::factory()->create();
        $workspace = $user->ensureHasDefaultWorkspace();

        app(DeleteUser::class)->delete($user);

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_deleting_a_user_only_removes_their_own_membership(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->ensureHasDefaultWorkspace();
        $member = User::factory()->create();
        UserWorkspace::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'user']);

        app(DeleteUser::class)->delete($owner);

        $this->assertDatabaseMissing('user_workspace', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('user_workspace', ['user_id' => $member->id, 'workspace_id' => $workspace->id]);
    }
}
