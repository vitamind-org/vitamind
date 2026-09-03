<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use VitaminD\Core\Actions\Plugins\BootPlugins;
use VitaminD\Core\Actions\Plugins\DiscoverPlugins;
use VitaminD\PluginSdk\RegisterPage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Plugin pages are registered into a process-static registry (in each
     * plugin's `boot()`), so without a flush here, a page registered by one
     * test — and any closures it captured over that test's database — leaks
     * into every later test in the same PHPUnit process, whether or not that
     * test cares about plugins at all.
     *
     * On a fresh test database, the app also boots (running Core's plugin
     * discovery/boot via its `booted()` hook) before RefreshDatabase creates
     * the schema, so plugins never got a chance to register in the first
     * place. Re-run discovery/boot now that the tables actually exist.
     *
     * This can't be done via RefreshDatabase's own `afterRefreshingDatabase()`
     * hook: PHP gives a trait method precedence over an inherited one, so
     * RefreshDatabase's own (no-op) version would shadow an override placed
     * here rather than call it.
     *
     * `DiscoverPlugins`/`BootPlugins` only re-boots plugins tracked in the
     * `plugins` table via `PluginInterface` (Local plugins, and
     * Composer/GitHub plugins an admin has installed+enabled) — it has no
     * knowledge of "always-on" Composer package providers such as
     * `vitamind/archive-plugin`'s `ArchiveServiceProvider`, which register
     * pages the same way but boot exactly once, via Laravel's normal
     * provider lifecycle inside `parent::setUp()` above, not through this
     * engine at all. Flushing would erase those for the rest of the test
     * with nothing to repopulate them, so anything the fresh boot already
     * registered is snapshotted first and restored for any key the
     * PluginInterface-driven re-boot below didn't itself re-supply.
     *
     * Only pages with no `tabs()` are restored, though — that's what makes
     * this safe. A `tabs()`-less page (the "custom-link" registration mode)
     * carries nothing but static strings/route names, so replaying it here
     * is inert. A page WITH `tabs()` carries `RegisterDataTable`/`Filter`
     * closures that query real models (see `App\Plugins\MockProduct\Plugin`
     * for an example), and whether those closures are safe to run depends
     * entirely on this specific test's database state — exactly the
     * cross-test leak this whole flush exists to prevent. Those must keep
     * flowing only through the `PluginInterface`/`BootPlugins` path above,
     * which runs solely for tests that opted into `RefreshDatabase`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $alwaysOnPages = RegisterPage::get();

        RegisterPage::flush();

        if (isset(class_uses_recursive(static::class)[RefreshDatabase::class])) {
            app(DiscoverPlugins::class)->handle();
            app(BootPlugins::class)->handle();
        }

        foreach ($alwaysOnPages as $key => $page) {
            if ($page->getTabs() === [] && RegisterPage::find($key) === null) {
                $page->register();
            }
        }
    }
}
