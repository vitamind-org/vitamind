<?php

namespace VitaminD\PluginSdk\Tests\DTOs;

use VitaminD\PluginSdk\Tests\TestCase;
use VitaminD\PluginSdk\DTOs\DynamicField;

class DynamicFieldTest extends TestCase
{
    public function test_field_creation_with_default_properties(): void
    {
        $field = DynamicField::make('username');
        $data = $field->toArray();

        $this->assertEquals('username', $data['name']);
        $this->assertEquals('text', $data['type']);
        $this->assertEquals('', $data['label']);
        $this->assertNull($data['default']);
        $this->assertNull($data['placeholder']);
        $this->assertNull($data['description']);
        $this->assertNull($data['options']);
        $this->assertNull($data['optionLabels']);
        $this->assertNull($data['link']);
        $this->assertNull($data['className']);
        $this->assertNull($data['componentProps']);
    }

    public function test_field_type_setters(): void
    {
        $this->assertEquals('component', DynamicField::make('f')->component()->toArray()['type']);
        $this->assertEquals('text', DynamicField::make('f')->text()->toArray()['type']);
        $this->assertEquals('number', DynamicField::make('f')->number()->toArray()['type']);
        $this->assertEquals('password', DynamicField::make('f')->password()->toArray()['type']);
        $this->assertEquals('password-with-toggle', DynamicField::make('f')->passwordWithToggle()->toArray()['type']);
        $this->assertEquals('textarea', DynamicField::make('f')->textarea()->toArray()['type']);
        $this->assertEquals('select', DynamicField::make('f')->select()->toArray()['type']);
        $this->assertEquals('checkbox', DynamicField::make('f')->checkbox()->toArray()['type']);
        $this->assertEquals('alert', DynamicField::make('f')->alert()->toArray()['type']);
    }

    public function test_field_fluent_builders(): void
    {
        $field = DynamicField::make('test_field')
            ->name('new_name')
            ->label('Test Label')
            ->default('default_value')
            ->placeholder('Enter text')
            ->description('This is a test field')
            ->options(['a' => 'A', 'b' => 'B'])
            ->className('col-span-2')
            ->componentProps(['disabled' => true]);

        $data = $field->toArray();

        $this->assertEquals('new_name', $data['name']);
        $this->assertEquals('Test Label', $data['label']);
        $this->assertEquals('default_value', $data['default']);
        $this->assertEquals('Enter text', $data['placeholder']);
        $this->assertEquals('This is a test field', $data['description']);
        $this->assertEquals(['a' => 'A', 'b' => 'B'], $data['options']);
        $this->assertEquals('col-span-2', $data['className']);
        $this->assertEquals(['disabled' => true], $data['componentProps']);
    }

    public function test_field_link(): void
    {
        $field = DynamicField::make('website')->link('Google', 'https://google.com');
        $data = $field->toArray();

        $this->assertEquals([
            'label' => 'Google',
            'url' => 'https://google.com',
        ], $data['link']);
    }
}
