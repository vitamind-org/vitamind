<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterPageGroup;

class RegisterPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear the static registry before each test
        $reflection = new \ReflectionClass(RegisterPage::class);
        $property = $reflection->getProperty('registry');
        $property->setValue([]);

        RegisterPageGroup::flush();
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

    public function test_placement_accepts_footer(): void
    {
        $page = RegisterPage::make('footer_page')->placement('footer');

        $this->assertEquals('footer', $page->toArray()['placement']);
        $this->assertNull($page->toArray()['group']);
    }

    public function test_placement_settings_and_admin_do_not_require_a_registered_group(): void
    {
        // Backward compatibility: placement('settings')/placement('admin')
        // (and the adminOnly()-implied default) must keep working exactly
        // as before, with no RegisterPageGroup ever registered — this is
        // what HandleInertiaRequestsPluginPagesTest already relies on.
        $settingsPage = RegisterPage::make('settings_sugar_page')->placement('settings');
        $adminPage = RegisterPage::make('admin_sugar_page')->adminOnly(true);

        $this->assertEquals('settings', $settingsPage->toArray()['group']);
        $this->assertEquals('settings', $settingsPage->toArray()['placement']);
        $this->assertEquals('admin', $adminPage->toArray()['group']);
        $this->assertEquals('admin', $adminPage->toArray()['placement']);
    }

    public function test_group_resolves_when_registered(): void
    {
        RegisterPageGroup::make('whatsapp')->title('WhatsApp')->icon('message-circle')->register();

        $page = RegisterPage::make('whatsapp_numbers')->group('whatsapp')->adminOnly(false);

        $this->assertEquals('whatsapp', $page->toArray()['group']);
        $this->assertEquals('main', $page->toArray()['placement']);
    }

    public function test_group_throws_when_not_registered(): void
    {
        $page = RegisterPage::make('orphan_page')->group('nonexistent')->adminOnly(false);

        $this->expectException(\InvalidArgumentException::class);
        $page->toArray();
    }

    public function test_order_defaults_to_zero(): void
    {
        $page = RegisterPage::make('unordered_page');

        $this->assertEquals(0, $page->toArray()['order']);
        $this->assertEquals(0, $page->getOrder());
    }

    public function test_order_can_be_set(): void
    {
        $page = RegisterPage::make('ordered_page')->order(50);

        $this->assertEquals(50, $page->toArray()['order']);
        $this->assertEquals(50, $page->getOrder());
    }

    public function test_external_defaults_to_false(): void
    {
        $page = RegisterPage::make('internal_page');

        $this->assertFalse($page->toArray()['external']);
    }

    public function test_external_can_be_set(): void
    {
        $page = RegisterPage::make('external_page')
            ->href('https://example.com')
            ->placement('footer')
            ->external(true);

        $this->assertTrue($page->toArray()['external']);
    }

    public function test_hidden_defaults_to_visible(): void
    {
        $page = RegisterPage::make('visible_page');

        $this->assertFalse($page->isHidden());
    }

    public function test_hidden_closure_is_evaluated_lazily(): void
    {
        $state = (object) ['hidden' => false];
        $page = RegisterPage::make('conditionally_hidden_page')->hidden(fn () => $state->hidden);

        $this->assertFalse($page->isHidden());

        $state->hidden = true;
        $this->assertTrue($page->isHidden());
    }
}
