<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Workspace\Models\Workspace;

class MassAssignmentTest extends TestCase
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

    public function test_folder_create_request_cannot_assign_owner_or_workspace(): void
    {
        $user = $this->onboardedUser();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post('/archive/folders', [
            'name' => 'Docs',
            'owner_id' => $otherUser->id,
            'workspace_id' => 999999,
        ])->assertSessionHasNoErrors();

        $folder = Folder::firstOrFail();
        $this->assertSame($user->id, $folder->owner_id);
        $this->assertSame($user->current_workspace_id, $folder->workspace_id);
        $this->assertNotSame(999999, $folder->workspace_id);
    }

    public function test_file_upload_request_cannot_assign_owner_or_workspace(): void
    {
        Storage::fake('local');
        $user = $this->onboardedUser();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post('/archive/files', [
            'file' => UploadedFile::fake()->image('photo.jpg', 10, 10),
            'owner_id' => $otherUser->id,
            'workspace_id' => 999999,
        ])->assertSessionHasNoErrors();

        $file = File::firstOrFail();
        $this->assertSame($user->id, $file->owner_id);
        $this->assertSame($user->current_workspace_id, $file->workspace_id);
        $this->assertNotSame(999999, $file->workspace_id);
    }
}
