<?php

namespace VitaminD\PluginSdk\Tests\Concerns;

use App\Models\User;
use VitaminD\PluginSdk\Tests\Fixtures\WorkspaceScopedRecord;
use VitaminD\PluginSdk\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class BelongsToWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Arbitrary workspace ids: `users.current_workspace_id` carries no foreign
     * key, and the trait only ever reads the integer. Not creating real
     * Workspace records here is the point — it proves the SDK trait needs
     * nothing from the workspace plugin.
     */
    private const int WORKSPACE_A = 101;

    private const int WORKSPACE_B = 202;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sdk_workspace_scoped_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('title');
            $table->timestamps();
        });
    }

    private function actingAsUserInWorkspace(?int $workspaceId): User
    {
        $user = User::factory()->create(['current_workspace_id' => $workspaceId]);
        $this->actingAs($user);

        return $user;
    }

    public function test_create_stamps_the_current_workspace(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);

        $record = WorkspaceScopedRecord::create(['title' => 'scoped']);

        $this->assertSame(self::WORKSPACE_A, (int) $record->workspace_id);
    }

    public function test_reads_are_limited_to_the_current_workspace(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);
        WorkspaceScopedRecord::create(['title' => 'belongs to A']);

        $this->actingAsUserInWorkspace(self::WORKSPACE_B);
        WorkspaceScopedRecord::create(['title' => 'belongs to B']);

        $visible = WorkspaceScopedRecord::pluck('title')->all();

        $this->assertSame(['belongs to B'], $visible);
    }

    public function test_records_from_another_workspace_cannot_be_fetched_by_id(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);
        $recordInA = WorkspaceScopedRecord::create(['title' => 'belongs to A']);

        $this->actingAsUserInWorkspace(self::WORKSPACE_B);

        // This is what stops the generic plugin CRUD controller from updating
        // or deleting another workspace's row: the lookup itself misses.
        $this->assertNull(WorkspaceScopedRecord::find($recordInA->id));
    }

    public function test_an_explicitly_set_workspace_is_not_overwritten(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);

        $record = new WorkspaceScopedRecord(['title' => 'explicit']);
        $record->workspace_id = self::WORKSPACE_B;
        $record->save();

        $this->assertSame(self::WORKSPACE_B, (int) $record->fresh()->workspace_id);
    }

    public function test_workspace_id_is_not_mass_assignable(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);

        $record = WorkspaceScopedRecord::create([
            'title' => 'attempted hijack',
            'workspace_id' => self::WORKSPACE_B,
        ]);

        $this->assertSame(self::WORKSPACE_A, (int) $record->workspace_id);
    }

    public function test_global_scope_can_be_bypassed_deliberately(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);
        WorkspaceScopedRecord::create(['title' => 'belongs to A']);

        $this->actingAsUserInWorkspace(self::WORKSPACE_B);
        WorkspaceScopedRecord::create(['title' => 'belongs to B']);

        $all = WorkspaceScopedRecord::withoutGlobalScope('workspace')->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['belongs to A', 'belongs to B'], $all);
    }

    public function test_trait_is_inert_when_the_workspaces_feature_is_disabled(): void
    {
        Config::set('vitamin-d.features.workspaces', false);

        $this->actingAsUserInWorkspace(self::WORKSPACE_A);
        $first = WorkspaceScopedRecord::create(['title' => 'global one']);

        $this->actingAsUserInWorkspace(self::WORKSPACE_B);
        WorkspaceScopedRecord::create(['title' => 'global two']);

        $this->assertNull($first->workspace_id);
        $this->assertEqualsCanonicalizing(
            ['global one', 'global two'],
            WorkspaceScopedRecord::pluck('title')->all(),
        );
    }

    public function test_unauthenticated_access_is_unscoped_rather_than_empty(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);
        WorkspaceScopedRecord::create(['title' => 'belongs to A']);

        // Console commands and queued jobs run with no authenticated user;
        // they must still see the data they were written to operate on.
        auth()->forgetGuards();

        $this->assertCount(1, WorkspaceScopedRecord::all());
    }

    public function test_user_without_a_current_workspace_is_unscoped(): void
    {
        $this->actingAsUserInWorkspace(self::WORKSPACE_A);
        WorkspaceScopedRecord::create(['title' => 'belongs to A']);

        $this->actingAsUserInWorkspace(null);

        $this->assertCount(1, WorkspaceScopedRecord::all());
    }
}
