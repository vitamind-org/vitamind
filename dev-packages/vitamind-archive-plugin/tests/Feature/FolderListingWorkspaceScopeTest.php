<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Regression coverage for a reported leak: switching the acting user's
 * current workspace still showed folders/files created under a *different*
 * workspace, as long as the viewer was authorized to open them at all (e.g.
 * as their own `user`/`workspace`-visibility items). `ChecksVisibility`
 * intentionally authorizes those regardless of the viewer's current
 * workspace (design.md D3) — that's correct for direct links/downloads —
 * but the archive *browser* itself must still only surface items that
 * belong to the workspace being browsed. See `ArchiveScope::
 * scopeToCurrentWorkspace()`.
 */
class FolderListingWorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create();
        $workspaceA = Workspace::create(['name' => 'workspace-a-'.Str::random(8)]);
        $workspaceA->users()->create(['user_id' => $user->id, 'is_default' => true]);
        $user->update(['current_workspace_id' => $workspaceA->id]);

        return $user->fresh();
    }

    private function makeFolder(User $owner, ?int $workspaceId, array $attributes = []): Folder
    {
        $folder = new Folder(array_merge([
            'name' => 'Folder',
            'visibility' => 'user',
        ], $attributes));
        $folder->owner_id = $owner->id;
        $folder->workspace_id = $workspaceId;
        $folder->save();

        return $folder;
    }

    private function makeFile(User $owner, ?int $workspaceId, array $attributes = []): File
    {
        $file = new File(array_merge([
            'original_name' => 'report.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'disk' => 'local',
            'path' => 'ab/cd/'.Str::uuid().'.pdf',
            'visibility' => 'user',
        ], $attributes));
        $file->owner_id = $owner->id;
        $file->workspace_id = $workspaceId;
        $file->save();

        return $file;
    }

    public function test_own_folder_created_in_another_workspace_does_not_leak_into_current_workspace_listing(): void
    {
        $user = $this->onboardedUser();
        $workspaceB = Workspace::create(['name' => 'workspace-b-'.Str::random(8)]);
        $workspaceB->users()->create(['user_id' => $user->id]);

        // Owned by $user, but created under workspace B — the reported
        // repro: create with visibility "Only me" in one workspace, then
        // switch to another where the user is also a genuine member.
        $leakyFolder = $this->makeFolder($user, $workspaceB->id, ['name' => 'From Workspace B', 'visibility' => 'user']);
        $ownFolder = $this->makeFolder($user, $user->current_workspace_id, ['name' => 'From Workspace A', 'visibility' => 'user']);

        $response = $this->actingAs($user)->get('/archive');

        $response->assertInertia(fn ($page) => $page
            ->has('folders', 1)
            ->where('folders.0.name', $ownFolder->name)
        );

        $this->assertNotEquals($leakyFolder->workspace_id, $user->current_workspace_id);
    }

    public function test_own_file_with_workspace_visibility_created_in_another_workspace_does_not_leak(): void
    {
        $user = $this->onboardedUser();
        $workspaceB = Workspace::create(['name' => 'workspace-b-'.Str::random(8)]);
        $workspaceB->users()->create(['user_id' => $user->id]);

        $this->makeFile($user, $workspaceB->id, ['original_name' => 'from-b.pdf', 'visibility' => 'workspace']);
        $ownFile = $this->makeFile($user, $user->current_workspace_id, ['original_name' => 'from-a.pdf', 'visibility' => 'workspace']);

        $response = $this->actingAs($user)->get('/archive');

        $response->assertInertia(fn ($page) => $page
            ->has('files', 1)
            ->where('files.0.original_name', $ownFile->original_name)
        );
    }

    public function test_unscoped_folder_with_null_workspace_id_still_appears_in_every_workspace(): void
    {
        $user = $this->onboardedUser();

        $unscoped = $this->makeFolder($user, null, ['name' => 'Unscoped', 'visibility' => 'user']);

        $response = $this->actingAs($user)->get('/archive');

        $response->assertInertia(fn ($page) => $page
            ->has('folders', 1)
            ->where('folders.0.name', $unscoped->name)
        );
    }
}
