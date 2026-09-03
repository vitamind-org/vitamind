<?php

namespace VitaminD\PluginSdk;

/**
 * Registers one main-sidebar entry that owns its own second-nav, fanning out
 * to every `RegisterPage` that joins it via `RegisterPage::group($key)`. See
 * `docs/plugin-development/menu-registration.md` for the full pattern (e.g.
 * a "WhatsApp" entry grouping "My Numbers" and "Contacts" pages).
 *
 * A group's own main-sidebar link resolves to its first-registered member
 * page — there is no separate "landing page" concept — computed where
 * `pluginPages` is built (`HandleInertiaRequests`), not here, since it
 * requires correlating against the `RegisterPage` registry.
 */
class RegisterPageGroup
{
    private string $title = '';

    private string $icon = 'package';

    private ?\Closure $hidden = null;

    private int $order = 0;

    private static array $registry = [];

    public function __construct(
        private readonly string $key,
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

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * A no-arg closure, evaluated lazily once per request. When it returns
     * `true`, this group's own entry is excluded from `pluginPages` for that
     * request, and so is every `RegisterPage` that joined it — there would
     * be no nav path left to reach them.
     */
    public function hidden(\Closure $callback): self
    {
        $this->hidden = $callback;

        return $this;
    }

    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function isHidden(): bool
    {
        return $this->hidden !== null && (bool) ($this->hidden)();
    }

    public function register(): void
    {
        self::$registry[$this->key] = $this;
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
}
