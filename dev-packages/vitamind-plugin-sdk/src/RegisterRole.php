<?php

namespace VitaminD\PluginSdk;

use Illuminate\Support\Str;

/**
 * Registers a role a plugin or host application declares in code, mirroring
 * the fluent-builder-plus-static-registry pattern already used by
 * `RegisterPage`/`RegisterPageGroup`. See
 * `docs/plugin-development/role-registration.md`.
 *
 * The key passed to `make()` is a short, package-local name; the key
 * actually stored in the registry (and later written to `user_roles.role`)
 * is automatically prefixed with an identifier derived from whichever
 * package called `register()`, so two packages can each register a role
 * called e.g. `owner` without colliding — no explicit namespace argument
 * required.
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
     * Resolves the caller's package identifier from the immediate caller's
     * namespace (a `debug_backtrace()` lookup, resolved once here) and
     * stores this role under `{prefix}.{shortKey}` in the registry.
     */
    public function register(): void
    {
        // Frame 0 is this method's own frame (register()); frame 1 is
        // whoever called ->register() on the builder. Resolved inline here
        // (rather than in a helper) so that caller is exactly one frame up.
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callerClass = $trace[1]['class'] ?? null;

        $this->key = self::prefixFromClass($callerClass).'.'.$this->shortKey;

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

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
        ];
    }

    /**
     * Convention-based: for a class living under `...\Plugins\{Name}\...`
     * (every VitaminD plugin, whether shipped in dev-packages/ under
     * `VitaminD\Plugins\{Name}` or a local app plugin under
     * `App\Plugins\{Name}`), the identifier is `{Name}` kebab-cased.
     * Anything else falls back to the second namespace segment (or the
     * first, or `app` for a caller with no resolvable class at all — e.g. a
     * closure).
     */
    private static function prefixFromClass(?string $class): string
    {
        if ($class === null || $class === '') {
            return 'app';
        }

        $segments = explode('\\', $class);
        array_pop($segments);

        $pluginsIndex = array_search('Plugins', $segments, true);
        if ($pluginsIndex !== false && isset($segments[$pluginsIndex + 1])) {
            return Str::kebab($segments[$pluginsIndex + 1]);
        }

        $fallback = $segments[1] ?? $segments[0] ?? 'app';

        return Str::kebab($fallback);
    }
}
