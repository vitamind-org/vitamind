<?php

namespace VitaminD\Plugins\Archive\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Policies\FilePolicy;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Mirrors FolderPolicyTest exactly — files and folders share identical
 * visibility semantics via ChecksVisibility (design.md D3).
 */
class FilePolicyTest extends TestCase
{
    use RefreshDatabase;

    private FilePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FilePolicy;
    }

    private function makeFile(User $owner, array $attributes = []): File
    {
        $file = new File(array_merge([
            'original_name' => 'report.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'disk' => 'local',
            'path' => 'ab/cd/uuid.pdf',
            'visibility' => 'user',
        ], $attributes));
        $file->owner_id = $owner->id;
        $file->workspace_id = $attributes['workspace_id'] ?? null;
        $file->save();

        return $file;
    }

    public function test_user_visibility_is_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = $this->makeFile($owner, ['visibility' => 'user']);

        $this->assertTrue($this->policy->view($owner, $file));
        $this->assertFalse($this->policy->view($other, $file));
        $this->assertFalse($this->policy->view(null, $file));
    }

    public function test_workspace_visibility_requires_genuine_membership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $nonMember = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $member->id]);

        $file = $this->makeFile($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);

        $this->assertTrue($this->policy->view($member, $file));
        $this->assertFalse($this->policy->view($nonMember, $file));
    }

    public function test_workspace_visibility_with_null_workspace_is_inaccessible_except_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = $this->makeFile($owner, ['visibility' => 'workspace', 'workspace_id' => null]);

        $this->assertTrue($this->policy->view($owner, $file));
        $this->assertFalse($this->policy->view($other, $file));
    }

    public function test_changing_visibility_away_from_workspace_immediately_revokes_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $member->id]);
        $file = $this->makeFile($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);

        $this->assertTrue($this->policy->view($member, $file));

        $file->visibility = 'user';

        $this->assertFalse($this->policy->view($member, $file));
    }

    public function test_workspace_visibility_denied_cleanly_when_workspaces_feature_disabled(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $member->id]);

        $file = $this->makeFile($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);

        Config::set('vitamin-d.features.workspaces', false);

        $this->assertFalse($this->policy->view($member, $file));
    }

    public function test_update_and_delete_are_restricted_to_owner_regardless_of_visibility(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = $this->makeFile($owner, ['visibility' => 'workspace']);

        $this->assertTrue($this->policy->update($owner, $file));
        $this->assertTrue($this->policy->delete($owner, $file));
        $this->assertFalse($this->policy->update($other, $file));
        $this->assertFalse($this->policy->delete($other, $file));
    }
}
