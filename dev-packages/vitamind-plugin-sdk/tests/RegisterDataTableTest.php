<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\Column;
use VitaminD\PluginSdk\Filter;
use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\DTOs\DynamicForm;
use VitaminD\PluginSdk\DTOs\DynamicField;

class RegisterDataTableTest extends TestCase
{
    public function test_datatable_creation_with_fluent_builders(): void
    {
        $form = DynamicForm::make([
            DynamicField::make('name')->text(),
        ]);

        $column = Column::text('name')->label('Name');
        $filter = Filter::make('category');

        $dataTable = RegisterDataTable::make('users_table')
            ->model('App\\Models\\User')
            ->columns([$column])
            ->form($form)
            ->with(['roles', 'profile'])
            ->filters([$filter]);

        $this->assertEquals('App\\Models\\User', $dataTable->getModel());
        $this->assertEquals([['key' => 'name', 'label' => 'Name', 'sortable' => false, 'searchable' => false, 'type' => 'text', 'options' => []]], $dataTable->getColumns());
        $this->assertEquals($form, $dataTable->getForm());
        $this->assertEquals(['roles', 'profile'], $dataTable->getRelations());
        $this->assertEquals([$filter], $dataTable->getFilters());

        $expectedArray = [
            'key' => 'users_table',
            'model' => 'App\\Models\\User',
            'columns' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'sortable' => false,
                    'searchable' => false,
                    'type' => 'text',
                    'options' => [],
                ]
            ],
            'form' => [
                [
                    'type' => 'text',
                    'name' => 'name',
                    'label' => '',
                    'default' => null,
                    'placeholder' => null,
                    'description' => null,
                    'options' => null,
                    'optionLabels' => null,
                    'link' => null,
                    'className' => null,
                    'componentProps' => null,
                    'rules' => null,
                ]
            ],
            'filters' => [
                [
                    'key' => 'category',
                    'label' => 'Category',
                    'type' => 'select',
                    'options' => [],
                ]
            ],
        ];

        $this->assertEquals($expectedArray, $dataTable->toArray());
    }

    public function test_datatable_option_providers(): void
    {
        $dataTable = RegisterDataTable::make('products_table')
            ->optionProvider('status', function () {
                return ['active' => 'Active', 'inactive' => 'Inactive'];
            })
            ->optionProvider('category', function () {
                return [1 => 'Category A'];
            });

        $expectedOptions = [
            'status' => ['active' => 'Active', 'inactive' => 'Inactive'],
            'category' => [1 => 'Category A'],
        ];

        $this->assertEquals($expectedOptions, $dataTable->getOptions());
    }
}
