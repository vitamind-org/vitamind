<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\Column;

class ColumnTest extends TestCase
{
    public function test_column_text_creation(): void
    {
        $column = Column::text('user_name');
        $data = $column->toArray();

        $this->assertEquals('user_name', $data['key']);
        $this->assertEquals('User Name', $data['label']);
        $this->assertFalse($data['sortable']);
        $this->assertFalse($data['searchable']);
        $this->assertEquals('text', $data['type']);
        $this->assertEmpty($data['options']);
    }

    public function test_column_badge_creation(): void
    {
        $options = ['success' => 'Active', 'danger' => 'Inactive'];
        $column = Column::badge('status', $options);
        $data = $column->toArray();

        $this->assertEquals('status', $data['key']);
        $this->assertEquals('Status', $data['label']);
        $this->assertEquals('badge', $data['type']);
        $this->assertEquals($options, $data['options']);
    }

    public function test_column_label_override(): void
    {
        $column = Column::text('email')->label('Email Address');
        $data = $column->toArray();

        $this->assertEquals('Email Address', $data['label']);
    }

    public function test_column_sortable_and_searchable(): void
    {
        $column = Column::text('id')->sortable()->searchable();
        $data = $column->toArray();

        $this->assertTrue($data['sortable']);
        $this->assertTrue($data['searchable']);
    }

    public function test_column_sortable_and_searchable_explicit(): void
    {
        $column = Column::text('id')->sortable(false)->searchable(false);
        $data = $column->toArray();

        $this->assertFalse($data['sortable']);
        $this->assertFalse($data['searchable']);
    }
}
