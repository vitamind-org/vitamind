<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\RegisterPage;

class RegisterPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear the static registry before each test
        $reflection = new \ReflectionClass(RegisterPage::class);
        $property = $reflection->getProperty('registry');
        $property->setValue([]);
    }

    public function test_page_creation_and_fluent_methods(): void
    {
        $page = RegisterPage::make('settings_page')
            ->title('Settings')
            ->icon('cog')
            ->adminOnly(false);

        $this->assertEquals('settings_page', $page->toArray()['key']);
        $this->assertEquals('Settings', $page->toArray()['title']);
        $this->assertEquals('cog', $page->toArray()['icon']);
        $this->assertFalse($page->isAdminOnly());
        $this->assertFalse($page->toArray()['admin_only']);
    }

    public function test_page_admin_only_default(): void
    {
        $page = RegisterPage::make('admin_page');
        $this->assertTrue($page->isAdminOnly());
        $this->assertTrue($page->toArray()['admin_only']);
    }

    public function test_page_tabs(): void
    {
        $table = RegisterDataTable::make('tab_table');
        $page = RegisterPage::make('tabs_page')->tabs([
            'general' => $table,
        ]);

        $this->assertEquals(['general' => $table], $page->getTabs());
        $this->assertArrayHasKey('general', $page->toArray()['tabs']);
        $this->assertEquals($table->toArray(), $page->toArray()['tabs']['general']);
    }

    public function test_page_registry_registration(): void
    {
        $pageA = RegisterPage::make('page_a');
        $pageB = RegisterPage::make('page_b');

        $pageA->register();
        $pageB->register();

        $this->assertCount(2, RegisterPage::get());
        $this->assertSame($pageA, RegisterPage::find('page_a'));
        $this->assertSame($pageB, RegisterPage::find('page_b'));
        $this->assertNull(RegisterPage::find('page_c'));
    }

    public function test_tabs_mode_href_resolves_to_plugins_page_route(): void
    {
        $page = RegisterPage::make('tabs_href_page')->tabs([
            'general' => RegisterDataTable::make('general'),
        ]);

        $this->assertEquals(route('plugins.page', 'tabs_href_page'), $page->toArray()['href']);
    }

    public function test_route_mode_href_resolves_via_laravel_route_helper(): void
    {
        $page = RegisterPage::make('archive')->route('dashboard');

        $this->assertEquals(route('dashboard'), $page->toArray()['href']);
    }

    public function test_href_mode_href_is_used_verbatim(): void
    {
        $page = RegisterPage::make('archive')->href('/archive');

        $this->assertEquals('/archive', $page->toArray()['href']);
    }

    public function test_register_throws_when_tabs_and_route_are_both_set(): void
    {
        $page = RegisterPage::make('conflicting_page')
            ->tabs(['general' => RegisterDataTable::make('general')])
            ->route('dashboard');

        $this->expectException(\InvalidArgumentException::class);
        $page->register();
    }

    public function test_register_throws_when_tabs_and_href_are_both_set(): void
    {
        $page = RegisterPage::make('conflicting_page')
            ->tabs(['general' => RegisterDataTable::make('general')])
            ->href('/archive');

        $this->expectException(\InvalidArgumentException::class);
        $page->register();
    }

    public function test_description_defaults_to_null(): void
    {
        $page = RegisterPage::make('no_description_page');

        $this->assertNull($page->toArray()['description']);
    }

    public function test_description_can_be_set(): void
    {
        $page = RegisterPage::make('described_page')
            ->description('Manage your uploaded folders and files.');

        $this->assertEquals('Manage your uploaded folders and files.', $page->toArray()['description']);
    }

    public function test_placement_defaults_to_main_when_not_admin_only(): void
    {
        $page = RegisterPage::make('main_page')->adminOnly(false);

        $this->assertEquals('main', $page->toArray()['placement']);
    }

    public function test_placement_defaults_to_admin_when_admin_only(): void
    {
        $page = RegisterPage::make('admin_page_default')->adminOnly(true);

        $this->assertEquals('admin', $page->toArray()['placement']);
    }

    public function test_placement_can_be_set_explicitly(): void
    {
        $page = RegisterPage::make('settings_placed_page')
            ->adminOnly(false)
            ->placement('settings');

        $this->assertEquals('settings', $page->toArray()['placement']);
    }

    public function test_placement_rejects_invalid_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RegisterPage::make('bad_placement_page')->placement('sidebar');
    }
}
