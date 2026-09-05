<?php

namespace VitaminD\PluginSdk;

/**
 * Registers a role a plugin or host application declares in code, mirroring
 * the fluent-builder-plus-static-registry pattern already used by
 * `RegisterPage`/`RegisterPageGroup`. See
 * `docs/plugin-development/role-registration.md`.
 *
 * The key passed to `make()` is a short, package-local name; `register()`
 * requires the caller's own plugin key explicitly (see
 * `VitaminD\PluginSdk\Concerns\RegistersOwnRole`, used by both
 * `AbstractPlugin` and `PluginBase`) and stores the role under
 * `{pluginKey}.{shortKey}` — so two plugins can each register a role called
 * e.g. `owner` without colliding, without any implicit namespace-guessing.
 */
class RegisterRole
{
    private string $title = '';

    private ?string $key = null;

    private static array $registry = [];

    public function __construct(
        private readonly string $shortKey,
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getShortKey(): string
    {
        return $this->shortKey;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The prefixed registry key. Only meaningful after `register()` has run
     * — the prefix is derived from the caller of `register()`, not `make()`.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Stores this role under `{pluginKey}.{shortKey}` in the registry.
     * `$pluginKey` is required — callers get it from their own
     * `pluginDetails()['key']` via `RegistersOwnRole::registerRole()`,
     * rather than typing it by hand.
     */
    public function register(string $pluginKey): void
    {
        $this->key = self::keyFor($pluginKey, $this->shortKey);

        self::$registry[$this->key] = $this;
    }

    /**
     * Computes the `{pluginKey}.{shortKey}` registry key without touching
     * the registry — for plugin code outside the Plugin/ServiceProvider
     * class itself (models, policies, etc.) that needs to reference a role
     * it already registered, without hand-concatenating the join format or
     * re-typing the short key. See
     * `docs/plugin-development/role-registration.md`.
     */
    public static function keyFor(string $pluginKey, string $shortKey): string
    {
        return $pluginKey.'.'.$shortKey;
    }

    public static function get(): array
    {
        return self::$registry;
    }

    public static function find(string $key): ?self
    {
        return self::$registry[$key] ?? null;
    }

    /**
     * Clears the registry. See `RegisterPage::flush()` — the same
     * per-process-registry test-isolation footgun applies here.
     */
    public static function flush(): void
    {
        self::$registry = [];
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
        ];
    }
}
