<?php

namespace VitaminD\PluginSdk\DTOs;

use Spatie\LaravelData\Data;

/**
 * @typescript
 */
class DynamicForm extends Data
{
    /**
     * @param  array<int, DynamicField>  $fields
     */
    public function __construct(
        public array $fields = [],
    ) {}

    /**
     * @param  array<int, DynamicField>  $fields
     */
    public static function make(array $fields): self
    {
        return new self($fields);
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->fields as $field) {
            $fields[] = $field->toArray();
        }

        return $fields;
    }

    public function getFieldNames(): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $name = $field->name;
            if ($name) {
                $fields[] = $name;
            }
        }

        return $fields;
    }

    public function getFieldValidationRules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            if ($field->rules) {
                $rules[$field->name] = $field->rules;
            }
        }

        return $rules;
    }
}
