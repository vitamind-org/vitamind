<?php

namespace VitaminD\PluginSdk;

class RegisterPage
{
    private string $title = '';

    private string $icon = 'package';

    private bool $adminOnly = true;

    private array $tabs = [];

    private ?string $description = null;

    private ?string $placement = null;

    private ?string $routeName = null;

    private array $routeParams = [];

    private ?string $href = null;

    private ?string $group = null;

    private ?\Closure $hidden = null;

    private int $order = 0;

    private bool $external = false;

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

    private static array $registry = [];

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function adminOnly(bool $adminOnly = true): self
    {
        $this->adminOnly = $adminOnly;

        return $this;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    public function tabs(array $tabs): self
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function getTabs(): array
    {
        return $this->tabs;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Joins this page to a `RegisterPageGroup`, collapsing it (and any
     * sibling page in the same group) under one main-sidebar entry that owns
     * its own second-nav. Unlike the `placement('settings')`/`placement('admin')`
     * sugar (which resolves to the built-in `settings`/`admin` groups without
     * requiring them to be pre-registered, for backward compatibility), an
     * explicit `group()` call here is validated against
     * `RegisterPageGroup`'s registry at `toArray()` time — a typo'd or
     * never-registered group key throws rather than silently rendering an
     * orphaned entry.
     */
    public function group(string $key): self
    {
        $this->group = $key;

        return $this;
    }

    /**
     * A no-arg closure, evaluated lazily once per request (same timing as
     * `route()` in `resolveHref()`) — call `auth()->user()`, `request()`,
     * etc. inside it. When it returns `true`, this entry is excluded
     * entirely from the `pluginPages` payload for that request (see
     * `HandleInertiaRequests`), not merely flagged hidden for the client to
     * skip rendering.
     */
    public function hidden(\Closure $callback): self
    {
        $this->hidden = $callback;

        return $this;
    }

    /**
     * Sort key within whichever region this entry renders in (main rail,
     * footer, or a group's second-nav). Defaults to `0`, so entries that
     * never call this sort in registration order relative to each other.
     */
    public function order(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Marks this entry as an outbound link — the footer region renders it as
     * a plain anchor (`target="_blank"`) instead of an Inertia navigation.
     */
    public function external(bool $external = true): self
    {
        $this->external = $external;

        return $this;
    }

    /**
     * Points this page's link at a named route the plugin owns, instead of
     * the built-in `plugins.page` destination. Mutually exclusive with
     * `href()` — the two are just different ways of expressing the same
     * "custom link" concern, so only the last one called wins, resolved
     * lazily via Laravel's `route()` helper at `toArray()` time (per-request,
     * long after every provider has booted and every route is registered).
     */
    public function route(string $name, array $params = []): self
    {
        $this->routeName = $name;
        $this->routeParams = $params;
        $this->href = null;

        return $this;
    }

    /**
     * Points this page's link at a pre-resolved URL. See `route()`'s
     * docblock — the two are mutually exclusive.
     */
    public function href(string $url): self
    {
        $this->href = $url;
        $this->routeName = null;
        $this->routeParams = [];

        return $this;
    }

    /**
     * Explicitly chooses which of the app's plugin-aware navs this page's
     * entry appears in. When never called, `toArray()` falls back to the
     * implicit rule every consumer already applied before this method
     * existed: `adminOnly(true)` → `admin`, otherwise → `main`.
     */
    public function placement(string $target): self
    {
        if (! in_array($target, ['main', 'admin', 'settings', 'footer'], true)) {
            throw new \InvalidArgumentException(
                "Invalid RegisterPage placement [{$target}]. Expected one of: main, admin, settings, footer."
            );
        }

        $this->placement = $target;

        return $this;
    }

    public function register(): void
    {
        if ($this->tabs !== [] && ($this->routeName !== null || $this->href !== null)) {
            throw new \InvalidArgumentException(
                "RegisterPage [{$this->key}]: tabs() and route()/href() cannot both be set on the same page."
            );
        }

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
     * Clears the registry. Pages are registered once per process (in each
     * plugin's `boot()`), so tests that re-run plugin discovery/boot between
     * cases need this to avoid a page registered by one test — and any
     * closures it captured — leaking into another.
     */
    public static function flush(): void
    {
        self::$registry = [];
    }

    /**
     * Resolves this page's link. A custom `href()`/`route()`, when set,
     * always wins over the generic `plugins.page` destination — `register()`
     * rejects any page that tries to combine one with `tabs()`, so at most
     * one of the two is ever in play here.
     */
    private function resolveHref(): string
    {
        if ($this->href !== null) {
            return $this->href;
        }

        if ($this->routeName !== null) {
            return route($this->routeName, $this->routeParams);
        }

        return route('plugins.page', $this->key);
    }

    /**
     * Resolves the group this page's entry belongs to, if any.
     *
     * An explicit `group()` call is validated against `RegisterPageGroup`'s
     * registry — anything else here is the pre-existing, unvalidated
     * `placement()`/`adminOnly()` sugar, preserved verbatim for backward
     * compatibility (a page can call `placement('settings')` without core
     * ever having registered a `settings` `RegisterPageGroup`, exactly as it
     * always could).
     */
    private function resolveGroup(): ?string
    {
        if ($this->group !== null) {
            if (RegisterPageGroup::find($this->group) === null) {
                throw new \InvalidArgumentException(
                    "RegisterPage [{$this->key}]: group [{$this->group}] has no corresponding RegisterPageGroup registration."
                );
            }

            return $this->group;
        }

        if ($this->placement === 'settings' || $this->placement === 'admin') {
            return $this->placement;
        }

        if ($this->placement === null && $this->adminOnly) {
            return 'admin';
        }

        return null;
    }

    private function resolvePlacement(?string $group): string
    {
        if ($this->placement === 'footer') {
            return 'footer';
        }

        return in_array($group, ['settings', 'admin'], true) ? $group : 'main';
    }

    public function isHidden(): bool
    {
        return $this->hidden !== null && (bool) ($this->hidden)();
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function toArray(): array
    {
        $tabsData = [];
        foreach ($this->tabs as $tabKey => $table) {
            $tabsData[$tabKey] = $table->toArray();
        }

        // Footer entries are flat (see menu-registration.md) — they never
        // participate in group membership even if `group()` was mistakenly
        // called alongside `placement('footer')`.
        $group = $this->placement === 'footer' ? null : $this->resolveGroup();

        return [
            'key' => $this->key,
            'title' => $this->title,
            'icon' => $this->icon,
            'admin_only' => $this->adminOnly,
            'tabs' => $tabsData,
            'description' => $this->description,
            'placement' => $this->resolvePlacement($group),
            'href' => $this->resolveHref(),
            'group' => $group,
            'order' => $this->order,
            'external' => $this->external,
        ];
    }
}
