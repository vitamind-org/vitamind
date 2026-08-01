<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\Filter;

class FilterTest extends TestCase
{
    public function test_filter_make_creation(): void
    {
        $filter = Filter::make('role_id');

        $this->assertEquals('role_id', $filter->getKey());
        $this->assertEquals('Role Id', $filter->getLabel());
        $this->assertEquals('select', $filter->getType());
        $this->assertEmpty($filter->getOptions());
    }

    public function test_filter_select_creation(): void
    {
        $filter = Filter::select('status');

        $this->assertEquals('status', $filter->getKey());
        $this->assertEquals('Status', $filter->getLabel());
        $this->assertEquals('select', $filter->getType());
    }

    public function test_filter_label_override(): void
    {
        $filter = Filter::make('user_type')->label('User Type Group');

        $this->assertEquals('User Type Group', $filter->getLabel());
    }

    public function test_filter_options(): void
    {
        $options = ['admin' => 'Administrator', 'user' => 'Regular User'];
        $filter = Filter::make('role')->options($options);

        $this->assertEquals($options, $filter->getOptions());
    }

    public function test_filter_option_provider(): void
    {
        $filter = Filter::make('category')->optionProvider(function () {
            return [1 => 'Category 1', 2 => 'Category 2'];
        });

        $this->assertEquals([1 => 'Category 1', 2 => 'Category 2'], $filter->getOptions());
    }

    public function test_filter_to_array(): void
    {
        $filter = Filter::make('status')
            ->label('Status Code')
            ->options([1 => 'Active', 0 => 'Inactive']);

        $expected = [
            'key' => 'status',
            'label' => 'Status Code',
            'type' => 'select',
            'options' => [1 => 'Active', 0 => 'Inactive'],
        ];

        $this->assertEquals($expected, $filter->toArray());
    }
}
