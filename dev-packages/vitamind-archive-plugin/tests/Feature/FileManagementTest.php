<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Regression coverage: the frontend only ever has a file's `uuid` (list
 * rows never expose the numeric id), so `/archive/files/{file}` must bind
 * by `uuid` like the download route does — not the default `id`, which a
 * uuid-shaped URL segment would never match, 404ing every rename/visibility
 * change/delete from the UI.
 */
class FileManagementTest extends TestCase
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

    private function makeFile(User $owner, array $attributes = []): File
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
        $file->save();

        return $file;
    }

    public function test_owner_can_rename_a_file_by_uuid(): void
    {
        $user = $this->onboardedUser();
        $file = $this->makeFile($user);

        $response = $this->actingAs($user)->patch("/archive/files/{$file->uuid}", [
            'original_name' => 'renamed.pdf',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('renamed.pdf', $file->fresh()->original_name);
    }

    public function test_owner_can_change_a_files_visibility_by_uuid(): void
    {
        $user = $this->onboardedUser();
        $file = $this->makeFile($user, ['visibility' => 'user']);

        $response = $this->actingAs($user)->patch("/archive/files/{$file->uuid}", [
            'visibility' => 'workspace',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('workspace', $file->fresh()->visibility);
    }

    public function test_owner_can_delete_a_file_by_uuid(): void
    {
        $user = $this->onboardedUser();
        $file = $this->makeFile($user);

        $response = $this->actingAs($user)->delete("/archive/files/{$file->uuid}");

        $response->assertSessionHasNoErrors();
        $this->assertSoftDeleted($file);
    }
}
