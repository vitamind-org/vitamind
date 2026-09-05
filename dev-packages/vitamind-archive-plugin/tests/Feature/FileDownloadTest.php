<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * The single authorized download/view endpoint (design.md D6) — every case
 * here confirms the response is produced by a live policy check against
 * current visibility state, never a cached/signed URL.
 */
class FileDownloadTest extends TestCase
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
        $file->workspace_id = $attributes['workspace_id'] ?? null;
        $file->save();

        return $file;
    }

    public function test_unauthenticated_request_is_denied(): void
    {
        // 'app'/'public' (which would allow guest access) are deferred —
        // see design.md Non-Goals. Both remaining tiers require
        // authentication, so the route sits behind `auth` middleware and
        // never reaches the policy check for a guest.
        $owner = $this->onboardedUser();
        $file = $this->makeFile($owner, ['visibility' => 'user']);

        $this->get("/archive/f/{$file->uuid}")->assertRedirect('/login');
    }

    public function test_owner_can_download_their_own_file(): void
    {
        Storage::fake('local');
        $owner = $this->onboardedUser();
        $file = $this->makeFile($owner, ['visibility' => 'user']);
        Storage::disk('local')->put($file->path, 'content');

        $this->actingAs($owner)->get("/archive/f/{$file->uuid}")->assertOk();
    }

    public function test_changing_visibility_away_from_workspace_immediately_revokes_the_download_link(): void
    {
        Storage::fake('local');
        $owner = $this->onboardedUser();
        $member = $this->onboardedUser();
        $workspace = Workspace::create(['name' => 'shared-'.Str::random(8)]);
        $workspace->users()->create(['user_id' => $member->id]);
        $file = $this->makeFile($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);
        Storage::disk('local')->put($file->path, 'content');

        // Same URL, no signature/token involved — accessible while a
        // genuine workspace member.
        $this->actingAs($member)->get("/archive/f/{$file->uuid}")->assertOk();

        $file->update(['visibility' => 'user']);

        // Immediately denied afterwards, with no separate revocation step.
        $this->actingAs($member)->get("/archive/f/{$file->uuid}")->assertForbidden();
    }
}
