<?php

namespace VitaminD\Core\Support;

use Closure;
use Illuminate\Http\Request;

/**
 * Lets plugins (e.g. the workspace plugin) contribute additional Inertia
 * shared props without core knowing anything about them.
 */
final class InertiaSharedData
{
    /** @var array<int, Closure(Request): array<string, mixed>> */
    private static array $resolvers = [];

    /**
     * @param  Closure(Request): array<string, mixed>  $resolver
     */
    public static function extend(Closure $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolve(Request $request): array
    {
        $data = [];

        foreach (self::$resolvers as $resolver) {
            $data = array_replace_recursive($data, $resolver($request));
        }

        return $data;
    }

    public static function flush(): void
    {
        self::$resolvers = [];
    }
}
