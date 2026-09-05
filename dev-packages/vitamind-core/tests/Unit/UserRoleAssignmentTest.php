<?php

namespace VitaminD\Core\Tests\Unit;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use VitaminD\Core\Actions\Role\AssignRole;
use VitaminD\Core\Actions\Role\RemoveRole;
use VitaminD\Core\Models\UserRoleAssignment;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_can_be_assigned_with_no_scope(): void
    {
        $user = User::factory()->create();

        UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'sales']);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role' => 'sales',
            'scope_type' => null,
            'scope_id' => null,
        ]);
    }

    public function test_a_user_can_hold_two_roles_at_once_in_the_same_scope(): void
    {
        $user = User::factory()->create();

        UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'admin-gudang', 'scope_type' => 'workspace', 'scope_id' => 1]);
        UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'sales', 'scope_type' => 'workspace', 'scope_id' => 1]);

        $this->assertSame(2, UserRoleAssignment::where('user_id', $user->id)->count());
        $this->assertTrue($user->hasRole('admin-gudang', 'workspace', 1));
        $this->assertTrue($user->hasRole('sales', 'workspace', 1));
    }

    public function test_duplicate_assignment_for_the_same_user_role_and_scope_is_rejected(): void
    {
        $user = User::factory()->create();

        UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'sales', 'scope_type' => 'workspace', 'scope_id' => 1]);

        $this->expectException(QueryException::class);

        UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'sales', 'scope_type' => 'workspace', 'scope_id' => 1]);
    }

    public function test_assign_role_action_is_idempotent(): void
    {
        $user = User::factory()->create();

        app(AssignRole::class)->assign($user, 'sales', 'workspace', 1);
        app(AssignRole::class)->assign($user, 'sales', 'workspace', 1);

        $this->assertSame(1, UserRoleAssignment::where('user_id', $user->id)->count());
    }

    public function test_remove_role_action_deletes_only_the_matching_assignment(): void
    {
        $user = User::factory()->create();
        app(AssignRole::class)->assign($user, 'sales', 'workspace', 1);
        app(AssignRole::class)->assign($user, 'sales', 'workspace', 2);

        app(RemoveRole::class)->remove($user, 'sales', 'workspace', 1);

        $this->assertFalse($user->hasRole('sales', 'workspace', 1));
        $this->assertTrue($user->hasRole('sales', 'workspace', 2));
    }
}
