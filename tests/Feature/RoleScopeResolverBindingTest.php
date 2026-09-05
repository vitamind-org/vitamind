<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Support\NullRoleScopeResolver;
use VitaminD\Plugins\Workspace\Support\WorkspaceRoleScopeResolver;

/**
 * Covers RoleScopeResolver's two-layer resolution order (design.md Decision
 * 3): an explicit `vitamin-d.role_scope_resolver` config override always
 * wins; left unset, whichever plugin bound its own default applies. This
 * app runs with the workspaces feature enabled (VITAMIND_FEATURE_WORKSPACES
 * in .env), so the automatic default in play here is
 * WorkspaceRoleScopeResolver — see NullRoleScopeResolverTest (vitamind-core)
 * for the plugin-less/global default in isolation.
 */
class RoleScopeResolverBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_active_plugins_default_is_bound_when_no_config_override_is_set(): void
    {
        config(['vitamin-d.role_scope_resolver' => null]);

        $this->assertInstanceOf(WorkspaceRoleScopeResolver::class, app(RoleScopeResolver::class));
    }

    public function test_a_config_override_takes_priority_over_the_plugin_default(): void
    {
        config(['vitamin-d.role_scope_resolver' => NullRoleScopeResolver::class]);

        $this->assertInstanceOf(NullRoleScopeResolver::class, app(RoleScopeResolver::class));
    }

    public function test_clearing_the_override_falls_back_to_the_automatic_default_again(): void
    {
        config(['vitamin-d.role_scope_resolver' => NullRoleScopeResolver::class]);
        $this->assertInstanceOf(NullRoleScopeResolver::class, app(RoleScopeResolver::class));

        config(['vitamin-d.role_scope_resolver' => null]);
        $this->assertInstanceOf(WorkspaceRoleScopeResolver::class, app(RoleScopeResolver::class));
    }
}
