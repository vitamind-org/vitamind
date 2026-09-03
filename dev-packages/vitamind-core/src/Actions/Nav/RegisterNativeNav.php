<?php

namespace VitaminD\Core\Actions\Nav;

use Illuminate\Support\Facades\Route;
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterPageGroup;

/**
 * Registers the app's own native nav entries — Dashboard, the Settings and
 * Admin sections, and the sidebar footer links — through the exact same
 * `RegisterPage`/`RegisterPageGroup` mechanism any plugin uses, instead of
 * hardcoding them in `app-sidebar.tsx`/`settings/layout.tsx`/`admin/layout.tsx`.
 * See `docs/plugin-development/menu-registration.md`.
 *
 * Every entry here always calls `register()` unconditionally — including
 * ones whose route may not exist in a given fork of this boilerplate (e.g.
 * Horizon/log-viewer, or a fork that omits the `dashboard` route). Gating
 * registration itself on `Route::has(...)` would be unreliable: this runs
 * inside `CoreServiceProvider::boot()`'s `$this->app->booted()` callback,
 * and `Route::has()` checked there is not guaranteed to already see routes
 * other providers register — the actual visibility gate is always the
 * `hidden()` closure instead, evaluated lazily per-request (by which point
 * routing has unquestionably finished). `RegisterPage::route()` itself never
 * resolves anything eagerly — it only stores a route name — so calling it
 * unconditionally is always safe; only `toArray()` (never invoked for a
 * hidden entry, see `ResolvePluginPages`) would throw for a route that
 * truly doesn't exist.
 *
 * `settings`/`admin` register with a deliberately high `order()` so they
 * keep sorting after any plugin's default (registration-order, effectively
 * `0`) main-sidebar entries — see ORDER_* constants. Their primary member
 * page (Profile, Users) likewise registers with a deliberately low `order()`
 * so it always wins that group's "first member" landing slot (see
 * `ResolvePluginPages`) even against a plugin page that also targets the
 * same group without setting an explicit order — registration order alone
 * isn't a reliable tiebreaker here, since a plugin registering directly in
 * its own `boot()` (the common pattern) runs before this class's callback.
 */
final readonly class RegisterNativeNav
{
    public const ORDER_DASHBOARD = -1000;

    public const ORDER_PRIMARY_GROUP_MEMBER = -100;

    public const ORDER_SETTINGS = 1000;

    public const ORDER_ADMIN = 1001;

    public function handle(): void
    {
        $this->registerDashboard();
        $this->registerSettings();
        $this->registerAdmin();
        $this->registerFooter();
    }

    private function registerDashboard(): void
    {
        RegisterPage::make('native-dashboard')
            ->title('Dashboard')
            ->icon('layout-dashboard')
            ->route('dashboard')
            ->hidden(fn () => ! Route::has('dashboard'))
            ->order(self::ORDER_DASHBOARD)
            ->adminOnly(false)
            ->register();
    }

    private function registerSettings(): void
    {
        RegisterPageGroup::make('settings')
            ->title('Settings')
            ->icon('cog')
            ->order(self::ORDER_SETTINGS)
            ->register();

        RegisterPage::make('native-profile')
            ->title('Profile')
            ->icon('user')
            ->route('profile')
            ->hidden(fn () => ! Route::has('profile'))
            ->group('settings')
            ->order(self::ORDER_PRIMARY_GROUP_MEMBER)
            ->adminOnly(false)
            ->register();

        RegisterPage::make('native-api-keys')
            ->title('API Keys')
            ->icon('key')
            ->route('api-keys')
            ->hidden(fn () => ! Route::has('api-keys'))
            ->group('settings')
            ->adminOnly(false)
            ->register();
    }

    private function registerAdmin(): void
    {
        RegisterPageGroup::make('admin')
            ->title('Admin')
            ->icon('settings-2')
            ->hidden(fn () => ! auth()->user()?->isAdmin())
            ->order(self::ORDER_ADMIN)
            ->register();

        RegisterPage::make('native-users')
            ->title('Users')
            ->icon('users')
            ->route('users')
            ->hidden(fn () => ! Route::has('users'))
            ->group('admin')
            ->order(self::ORDER_PRIMARY_GROUP_MEMBER)
            ->adminOnly(true)
            ->register();

        RegisterPage::make('native-plugins')
            ->title('Plugins')
            ->icon('plug')
            ->route('plugins')
            ->hidden(fn () => ! Route::has('plugins'))
            ->group('admin')
            ->adminOnly(true)
            ->register();
    }

    private function registerFooter(): void
    {
        RegisterPage::make('native-horizon')
            ->title('Horizon Dashboard')
            ->icon('list-end')
            ->route('horizon.index')
            ->hidden(fn () => ! Route::has('horizon.index') || ! auth()->user()?->isAdmin())
            ->placement('footer')
            ->order(0)
            ->adminOnly(true)
            ->register();

        RegisterPage::make('native-logs')
            ->title('Logs')
            ->icon('logs')
            ->route('log-viewer.index')
            ->hidden(fn () => ! Route::has('log-viewer.index') || ! auth()->user()?->isAdmin())
            ->placement('footer')
            ->order(10)
            ->adminOnly(true)
            ->register();

        RegisterPage::make('native-repository')
            ->title('Repository')
            ->icon('folder')
            ->href('https://github.com/vitodeploy/vitamin-d')
            ->placement('footer')
            ->external(true)
            ->order(20)
            ->adminOnly(false)
            ->register();

        RegisterPage::make('native-documentation')
            ->title('Documentation')
            ->icon('book-open')
            ->href('https://github.com/vitodeploy/vitamin-d')
            ->placement('footer')
            ->external(true)
            ->order(30)
            ->adminOnly(false)
            ->register();
    }
}
