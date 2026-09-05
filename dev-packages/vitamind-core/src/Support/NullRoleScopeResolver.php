<?php

namespace VitaminD\Core\Support;

use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Models\User;

/**
 * Default `RoleScopeResolver` binding: every role check resolves to a
 * global, unscoped assignment. Active whenever no tenancy-like plugin (or
 * config override) supplies its own resolver — a fresh single-tenant
 * install gets a fully working role system with no configuration.
 */
class NullRoleScopeResolver implements RoleScopeResolver
{
    public function resolve(User $user): array
    {
        return [
            'scope_type' => null,
            'scope_id' => null,
        ];
    }
}
