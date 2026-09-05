<?php

namespace VitaminD\Core\Tests\Unit;

use App\Models\User;
use Tests\TestCase;
use VitaminD\Core\Support\NullRoleScopeResolver;

class NullRoleScopeResolverTest extends TestCase
{
    public function test_resolves_a_global_null_scope(): void
    {
        $resolver = new NullRoleScopeResolver;

        $this->assertSame(
            ['scope_type' => null, 'scope_id' => null],
            $resolver->resolve(new User)
        );
    }
}
