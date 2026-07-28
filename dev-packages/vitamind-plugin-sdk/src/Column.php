<?php

namespace VitaminD\PluginSdk;

class Column
{
    public function __construct(
        private readonly string $key,
        private string $label = '',
        private bool $sortable = false,
        private bool $searchable = false,
        private string $type = 'text',
        private array $options = [],
    ) {}

    public static function text(string $key): self
    {
        return new self($key, '', false, false, 'text');
    }

    public static function badge(string $key, array $options = []): self
    {
        return new self($key, '', false, false, 'badge', $options);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?: ucwords(str_replace(['_', '.'], ' ', $this->key)),
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'type' => $this->type,
            'options' => $this->options,
        ];
    }
}
