<?php

namespace VitaminD\PluginSdk\Tests\DTOs;

use VitaminD\PluginSdk\Tests\TestCase;
use VitaminD\PluginSdk\DTOs\DynamicForm;
use VitaminD\PluginSdk\DTOs\DynamicField;

class DynamicFormTest extends TestCase
{
    public function test_form_creation_and_to_array(): void
    {
        $fieldA = DynamicField::make('title')->text();
        $fieldB = DynamicField::make('body')->textarea();

        $form = DynamicForm::make([$fieldA, $fieldB]);

        $expected = [
            $fieldA->toArray(),
            $fieldB->toArray(),
        ];

        $this->assertEquals($expected, $form->toArray());
    }

    public function test_form_field_names_extraction(): void
    {
        $fieldA = DynamicField::make('title')->text();
        $fieldB = DynamicField::make('body')->textarea();
        // DynamicField that doesn't have a name configured correctly (e.g. empty or name method overriding it)
        $fieldC = DynamicField::make('');

        $form = DynamicForm::make([$fieldA, $fieldB, $fieldC]);

        $this->assertEquals(['title', 'body'], $form->getFieldNames());
    }
}
