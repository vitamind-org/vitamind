<?php

namespace VitaminD\PluginSdk;

use VitaminD\PluginSdk\DTOs\DynamicForm;

class RegisterDataTable
{
    private ?DynamicForm $form = null;
    private array $columns = [];
    private array $relations = [];
    private array $optionProviders = [];
    private array $filters = [];

    public function __construct(
        private readonly string $key,
        private string $model = '',
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function form(DynamicForm $form): self
    {
        $this->form = $form;

        return $this;
    }

    public function with(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function optionProvider(string $fieldName, callable $callback): self
    {
        $this->optionProviders[$fieldName] = $callback;

        return $this;
    }

    public function getOptions(): array
    {
        $options = [];
        foreach ($this->optionProviders as $fieldName => $callback) {
            $options[$fieldName] = call_user_func($callback);
        }

        return $options;
    }

    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getColumns(): array
    {
        return array_map(fn($col) => $col instanceof Column ? $col->toArray() : $col, $this->columns);
    }

    public function getForm(): ?DynamicForm
    {
        return $this->form;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'model' => $this->model,
            'columns' => $this->getColumns(),
            'form' => $this->form ? $this->form->toArray() : null,
            'filters' => array_map(fn($f) => $f->toArray(), $this->filters),
        ];
    }
}
