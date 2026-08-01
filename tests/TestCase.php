<?php

namespace Tests;

use VitaminD\Core\Actions\Plugins\BootPlugins;
use VitaminD\Core\Actions\Plugins\DiscoverPlugins;
use VitaminD\PluginSdk\RegisterPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
     */
    protected function setUp(): void
    {
        parent::setUp();

        RegisterPage::flush();

        if (isset(class_uses_recursive(static::class)[RefreshDatabase::class])) {
            app(DiscoverPlugins::class)->handle();
            app(BootPlugins::class)->handle();
        }
    }
}
