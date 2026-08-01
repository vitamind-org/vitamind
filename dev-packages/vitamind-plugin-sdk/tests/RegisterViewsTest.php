<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterViews;

class RegisterViewsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear the config before each test
        config(['plugins.views' => []]);
    }

    public function test_register_view(): void
    {
        $viewName = 'my-plugin-view';
        $viewPath = '/path/to/my-plugin/views';

        $register = RegisterViews::make($viewName)->path($viewPath);
        $register->register();

        $registered = RegisterViews::get();
        $this->assertArrayHasKey($viewName, $registered);
        $this->assertEquals($viewPath, $registered[$viewName]);
        $this->assertEquals([$viewName => $viewPath], config('plugins.views'));
    }

    public function test_register_view_does_not_register_if_name_or_path_is_empty(): void
    {
        // Empty path
        RegisterViews::make('view-a')->path('')->register();
        $this->assertEmpty(RegisterViews::get());

        // Empty name
        RegisterViews::make('')->path('/some/path')->register();
        $this->assertEmpty(RegisterViews::get());
    }

    public function test_register_multiple_views(): void
    {
        RegisterViews::make('view-a')->path('/path/a')->register();
        RegisterViews::make('view-b')->path('/path/b')->register();

        $this->assertEquals([
            'view-a' => '/path/a',
            'view-b' => '/path/b',
        ], RegisterViews::get());
    }
}
