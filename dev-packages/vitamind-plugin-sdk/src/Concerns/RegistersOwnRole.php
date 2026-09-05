<?php

namespace VitaminD\PluginSdk\Concerns;

use LogicException;
use VitaminD\PluginSdk\RegisterRole;

/**
 * Shared by `AbstractPlugin` and `PluginBase` — the two plugin base classes
 * have no common ancestor, so this trait is how both get the same
 * "register a role under my own declared identity" behavior without
 * duplicating it. Requires the using class to implement `HasPluginDetails`
 * (via `PluginInterface` for `AbstractPlugin`, or directly for `PluginBase`).
 */
trait RegistersOwnRole
{
    abstract public function pluginDetails(): array;

    /**
     * Registers a role scoped to this plugin's own declared key — the
     * plugin author never retypes it.
     */
    protected function registerRole(string $shortKey, string $title): void
    {
        RegisterRole::make($shortKey)->title($title)->register($this->pluginKey());
    }

    /**
     * Turns a malformed `pluginDetails()` implementation into a clear
     * exception here, rather than a cryptic "undefined array key" notice
     * surfacing later at the point of use.
     */
    protected function pluginKey(): string
    {
        $key = $this->pluginDetails()['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new LogicException(static::class.'::pluginDetails() must return a non-empty "key".');
        }

        return $key;
    }
}
