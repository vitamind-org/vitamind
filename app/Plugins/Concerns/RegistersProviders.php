<?php

namespace App\Plugins\Concerns;

trait RegistersProviders
{
    protected static array $providers = [];

    public static function register(string $key, mixed $value): void
    {
        static::$providers[$key] = $value;
    }

    public static function all(): array
    {
        return static::$providers;
    }

    public static function get(string $key): mixed
    {
        return static::$providers[$key] ?? null;
    }
}
