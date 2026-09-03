<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterPageGroup;

class RegisterPageGroupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RegisterPageGroup::flush();
    }

    public function test_group_creation_and_fluent_methods(): void
    {
        $group = RegisterPageGroup::make('whatsapp')
            ->title('WhatsApp')
            ->icon('message-circle')
            ->order(10);

        $this->assertEquals('whatsapp', $group->getKey());
        $this->assertEquals('WhatsApp', $group->getTitle());
        $this->assertEquals('message-circle', $group->getIcon());
        $this->assertEquals(10, $group->getOrder());
        $this->assertFalse($group->isHidden());
    }

    public function test_order_defaults_to_zero(): void
    {
        $group = RegisterPageGroup::make('whatsapp');

        $this->assertEquals(0, $group->getOrder());
    }

    public function test_hidden_closure_is_evaluated_lazily(): void
    {
        $state = (object) ['hidden' => false];
        $group = RegisterPageGroup::make('whatsapp')->hidden(fn () => $state->hidden);

        $this->assertFalse($group->isHidden());

        $state->hidden = true;
        $this->assertTrue($group->isHidden());
    }

    public function test_group_registry_registration(): void
    {
        $groupA = RegisterPageGroup::make('group_a');
        $groupB = RegisterPageGroup::make('group_b');

        $groupA->register();
        $groupB->register();

        $this->assertCount(2, RegisterPageGroup::get());
        $this->assertSame($groupA, RegisterPageGroup::find('group_a'));
        $this->assertSame($groupB, RegisterPageGroup::find('group_b'));
        $this->assertNull(RegisterPageGroup::find('group_c'));
    }

    public function test_flush_clears_the_registry(): void
    {
        RegisterPageGroup::make('whatsapp')->register();
        $this->assertCount(1, RegisterPageGroup::get());

        RegisterPageGroup::flush();

        $this->assertCount(0, RegisterPageGroup::get());
    }
}
