<?php

namespace VitaminD\Plugins\Archive\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Archive\Policies\FolderPolicy;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * One case per visibility tier per the scenarios in
 * specs/vitamind-archive-plugin/spec.md. FilePolicyTest mirrors these
 * exactly, since both policies share identical semantics via
 * ChecksVisibility.
 */
class FolderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FolderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FolderPolicy;
    }

    private function makeFolder(User $owner, array $attributes = []): Folder
    {
        $folder = new Folder(array_merge([
            'name' => 'Folder',
            'visibility' => 'user',
        ], $attributes));
        $folder->owner_id = $owner->id;
        $folder->workspace_id = $attributes['workspace_id'] ?? null;
        $folder->save();

        return $folder;
    }

    public function test_user_visibility_is_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = $this->makeFolder($owner, ['visibility' => 'user']);

        $this->assertTrue($this->policy->view($owner, $folder));
        $this->assertFalse($this->policy->view($other, $folder));
        $this->assertFalse($this->policy->view(null, $folder));
    }

    public function test_workspace_visibility_requires_genuine_membership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $nonMember = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $member->id]);

        $folder = $this->makeFolder($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);

        $this->assertTrue($this->policy->view($member, $folder));
        $this->assertFalse($this->policy->view($nonMember, $folder));
    }

    public function test_workspace_visibility_with_null_workspace_is_inaccessible_except_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = $this->makeFolder($owner, ['visibility' => 'workspace', 'workspace_id' => null]);

        $this->assertTrue($this->policy->view($owner, $folder));
        $this->assertFalse($this->policy->view($other, $folder));
    }

    public function test_changing_visibility_away_from_workspace_immediately_revokes_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $member->id]);
        $folder = $this->makeFolder($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);

        $this->assertTrue($this->policy->view($member, $folder));

        $folder->visibility = 'user';

        $this->assertFalse($this->policy->view($member, $folder));
    }

    public function test_workspace_visibility_denied_cleanly_when_workspaces_feature_disabled(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $member->id]);

        $folder = $this->makeFolder($owner, ['visibility' => 'workspace', 'workspace_id' => $workspace->id]);

        Config::set('vitamin-d.features.workspaces', false);

        $this->assertFalse($this->policy->view($member, $folder));
    }

    public function test_update_and_delete_are_restricted_to_owner_regardless_of_visibility(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = $this->makeFolder($owner, ['visibility' => 'workspace']);

        $this->assertTrue($this->policy->update($owner, $folder));
        $this->assertTrue($this->policy->delete($owner, $folder));
        $this->assertFalse($this->policy->update($other, $folder));
        $this->assertFalse($this->policy->delete($other, $folder));
    }
}
