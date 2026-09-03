<?php

namespace VitaminD\Plugins\Archive\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Workspace\Models\Workspace;

class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `EnsureWorkspaceOnboarded` (vitamind/workspace-plugin) is pushed onto
     * the global `web` middleware group whenever
     * `vitamin-d.features.workspaces` is on — forced true for this whole
     * monorepo's shared phpunit run — and redirects any authenticated user
     * with zero workspace memberships to the onboarding screen before this
     * plugin's own controllers ever run. Every authenticated request in
     * this file needs a real membership to reach `/archive/*` at all.
     */
    private function onboardedUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme-'.Str::random(8)]);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner', 'is_default' => true]);
        $user->update(['current_workspace_id' => $workspace->id]);

        return $user;
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->post('/archive/files', [
            'file' => UploadedFile::fake()->create('malware.php', 10),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, File::count());
    }

    /**
     * `->mimeType(...)` overrides what `UploadedFile::getMimeType()`
     * reports for a faked upload — standing in here for "real content is
     * detected as `text/plain`", the same signal
     * assertContentMatchesExtension() reads from a genuine upload's bytes
     * in production. `.jpg` is on the allow-list, but `text/plain` content
     * doesn't map back to it, so the cross-check must still reject it.
     */
    public function test_extension_disguising_content_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->post('/archive/files', [
            'file' => UploadedFile::fake()->create('photo.jpg', 10)->mimeType('text/plain'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, File::count());
    }

    public function test_valid_upload_stores_under_a_uuid_derived_path(): void
    {
        Storage::fake('local');
        $user = $this->onboardedUser();

        $response = $this->actingAs($user)->post('/archive/files', [
            'file' => UploadedFile::fake()->image('laporan Q3.jpg', 10, 10),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, File::count());

        $file = File::firstOrFail();
        $this->assertNotSame('laporan Q3.jpg', basename($file->path));
        $this->assertStringContainsString($file->uuid, $file->path);
        Storage::disk('local')->assertExists($file->path);
    }
}
