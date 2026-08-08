<?php

namespace VitaminD\Plugins\Realtime\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use VitaminD\Plugins\Realtime\Support\WorkspaceChannelAuthorization;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Exercises the Layer 2 helper against a real workspace-plugin membership
 * row. `vitamind/realtime-plugin` itself does not depend on
 * `vitamind/workspace-plugin` (design.md's D3) — this test relies on it
 * only because both are installed side by side in this monorepo, the same
 * way the shared phpunit.xml runs every dev-package's suite together.
 */
class WorkspaceChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_authorized(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        $this->assertTrue(WorkspaceChannelAuthorization::check($user, $workspace->id));
    }

    public function test_non_member_is_denied(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);

        $this->assertFalse(WorkspaceChannelAuthorization::check($user, $workspace->id));
    }

    public function test_denied_when_workspaces_feature_disabled(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        Config::set('vitamin-d.features.workspaces', false);

        $this->assertFalse(WorkspaceChannelAuthorization::check($user, $workspace->id));
    }

    public function test_denied_when_no_authenticated_user(): void
    {
        $workspace = Workspace::create(['name' => 'acme']);

        $this->assertFalse(WorkspaceChannelAuthorization::check(null, $workspace->id));
    }

    /**
     * `(int) $workspaceId` alone would silently accept these as the real
     * workspace ID below — e.g. `workspace.{$id}x.ping` and
     * `workspace.0{$id}.ping` would both check membership for workspace
     * `$id` instead of being rejected as malformed channel parameters.
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
                WorkspaceChannelAuthorization::check($user, $malformed),
                "Expected workspace ID \"{$malformed}\" to be rejected."
            );
        }
    }
}
