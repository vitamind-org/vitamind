<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterDataTable;

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
}
