<?php

namespace VitaminD\PluginSdk\Tests\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\PluginSdk\Support\WorkspaceMembership;
use VitaminD\PluginSdk\Tests\TestCase;

/**
 * `vitamind/plugin-sdk` itself does not depend on `vitamind/workspace-plugin`
 * — this test relies on it only because both are installed side by side in
 * this monorepo, the same way the shared phpunit.xml runs every
 * dev-package's suite together (see `WorkspaceChannelAuthorizationTest` in
 * `vitamind-realtime-plugin`, which this class is the shared home for).
 */
class WorkspaceMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_authorized(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        $this->assertTrue(WorkspaceMembership::check($user, $workspace->id));
    }

    public function test_non_member_is_denied(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);

        $this->assertFalse(WorkspaceMembership::check($user, $workspace->id));
    }

    /**
     * Proves the check verifies real membership rather than trusting
     * `current_workspace_id` — the wrong shortcut this primitive exists to
     * avoid (see design.md D3 in `add-archive-plugin`).
     */
    public function test_workspace_matching_current_workspace_id_without_membership_row_is_denied(): void
    {
        $workspace = Workspace::create(['name' => 'acme']);
        $user = User::factory()->create(['current_workspace_id' => $workspace->id]);

        $this->assertFalse(WorkspaceMembership::check($user, $workspace->id));
    }

    public function test_denied_when_workspaces_feature_disabled(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        Config::set('vitamin-d.features.workspaces', false);

        $this->assertFalse(WorkspaceMembership::check($user, $workspace->id));
    }

    public function test_denied_when_no_authenticated_user(): void
    {
        $workspace = Workspace::create(['name' => 'acme']);

        $this->assertFalse(WorkspaceMembership::check(null, $workspace->id));
    }

    /**
     * `(int) $workspaceId` alone would silently accept these as the real
     * workspace ID below — e.g. `"{$id}x"` and `"0{$id}"` would both check
     * membership for workspace `$id` instead of being rejected as
     * malformed input.
     */
    public function test_denied_for_non_canonical_workspace_id(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        $id = (string) $workspace->id;

        $malformedIds = [
            $id.'x',
            $id.'.0',
            '0'.$id,
            '-'.$id,
            '0',
            '',
        ];

        foreach ($malformedIds as $malformed) {
            $this->assertFalse(
                WorkspaceMembership::check($user, $malformed),
                "Expected workspace ID \"{$malformed}\" to be rejected."
            );
        }
    }
}
