<?php

namespace VitaminD\Plugins\Archive\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Archive\Policies\FolderPolicy;

/**
 * `vitamind/workspace-plugin` is installed side by side with this plugin in
 * the monorepo's shared test run (phpunit.xml forces
 * VITAMIND_FEATURE_WORKSPACES=true for every suite), the same practical
 * constraint noted in realtime-plugin's WorkspaceChannelAuthorizationTest —
 * so a real "no `workspaces` table present" HTTP-level test isn't possible
 * here. This instead exercises the actual behavioral guarantee at the
 * model/policy layer: with the feature flag off, `folders`/`files` still
 * exist and function, and every non-`workspace` tier behaves identically
 * to a workspace-enabled install, with `workspace`-visibility denied
 * cleanly rather than throwing.
 */
class NoWorkspacePluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('vitamin-d.features.workspaces', false);
    }

    public function test_migrations_create_the_plugins_own_tables(): void
    {
        $this->assertTrue(Schema::hasTable('folders'));
        $this->assertTrue(Schema::hasTable('files'));
    }

    public function test_user_tier_works_without_the_workspace_plugin(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $policy = new FolderPolicy;

        $userFolder = new Folder(['name' => 'Private', 'visibility' => 'user']);
        $userFolder->owner_id = $owner->id;
        $userFolder->save();
        $this->assertTrue($policy->view($owner, $userFolder));
        $this->assertFalse($policy->view($other, $userFolder));
    }

    public function test_workspace_visibility_is_denied_cleanly_with_no_exception(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $policy = new FolderPolicy;

        $workspaceFolder = new Folder(['name' => 'Team', 'visibility' => 'workspace']);
        $workspaceFolder->owner_id = $owner->id;
        $workspaceFolder->workspace_id = 1;
        $workspaceFolder->save();

        $this->assertFalse($policy->view($other, $workspaceFolder));
    }
}
