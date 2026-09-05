<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Workspace\Models\Workspace;

class FolderDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme-'.Str::random(8)]);
        $workspace->users()->create(['user_id' => $user->id, 'is_default' => true]);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }

    private function makeFolder(User $owner, array $attributes = []): Folder
    {
        $folder = new Folder(array_merge(['name' => 'Folder'], $attributes));
        $folder->owner_id = $owner->id;
        $folder->save();

        return $folder;
    }

    public function test_deleting_an_empty_folder_succeeds(): void
    {
        $user = $this->onboardedUser();
        $folder = $this->makeFolder($user);

        $response = $this->actingAs($user)->delete("/archive/folders/{$folder->uuid}");

        $response->assertSessionHasNoErrors();
        $this->assertSoftDeleted($folder);
    }

    public function test_deleting_a_folder_with_a_child_folder_is_rejected(): void
    {
        $user = $this->onboardedUser();
        $parent = $this->makeFolder($user, ['name' => 'Parent']);
        $this->makeFolder($user, ['name' => 'Child', 'parent_id' => $parent->id]);

        $response = $this->actingAs($user)->delete("/archive/folders/{$parent->uuid}");

        $response->assertSessionHasErrors('folder');
        $this->assertNotSoftDeleted($parent);
    }

    public function test_deleting_a_folder_with_a_file_is_rejected(): void
    {
        $user = $this->onboardedUser();
        $folder = $this->makeFolder($user, ['name' => 'Docs']);

        $file = new File([
            'original_name' => 'a.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'disk' => 'local',
            'path' => 'a/b/c.pdf',
            'folder_id' => $folder->id,
        ]);
        $file->owner_id = $user->id;
        $file->save();

        $response = $this->actingAs($user)->delete("/archive/folders/{$folder->uuid}");

        $response->assertSessionHasErrors('folder');
        $this->assertNotSoftDeleted($folder);
    }
}
