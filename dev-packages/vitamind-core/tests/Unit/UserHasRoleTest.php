<?php

namespace VitaminD\Core\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use VitaminD\Core\Actions\Role\AssignRole;
use VitaminD\Core\Support\NullRoleScopeResolver;

class UserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_checks_an_explicit_scope(): void
    {
        $user = User::factory()->create();
        app(AssignRole::class)->assign($user, 'sales', 'branch', 5);

        $this->assertTrue($user->hasRole('sales', 'branch', 5));
        $this->assertFalse($user->hasRole('sales', 'branch', 6));
        $this->assertFalse($user->hasRole('other-role', 'branch', 5));
    }

    public function test_has_role_falls_back_to_the_bound_resolver_when_scope_is_omitted(): void
    {
        $user = User::factory()->create();
        app(AssignRole::class)->assign($user, 'sales');

        // This app runs with the workspaces feature enabled, so the bound
        // RoleScopeResolver is workspace-scoped by default — with no
        // current_workspace_id set, it resolves to ['workspace', null], not
        // the global null/null pair the assignment above was stored under.
        // Forcing the config override to NullRoleScopeResolver proves
        // hasRole() genuinely consults whichever resolver is bound, rather
        // than assuming a global scope.
        $this->assertFalse($user->hasRole('sales'));

        config(['vitamin-d.role_scope_resolver' => NullRoleScopeResolver::class]);

        $this->assertTrue($user->hasRole('sales'));
    }

    public function test_has_role_with_an_explicit_scope_never_consults_the_resolver(): void
    {
        $user = User::factory()->create();
        app(AssignRole::class)->assign($user, 'sales', 'workspace', 1);

        $user->current_workspace_id = 999;

        $this->assertTrue($user->hasRole('sales', 'workspace', 1));
        $this->assertFalse($user->hasRole('sales'));
    }
}
