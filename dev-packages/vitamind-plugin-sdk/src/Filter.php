<?php

namespace VitaminD\PluginSdk;

class Filter
{
    private string $label = '';
    private string $type = 'select';
    private array $options = [];
    private mixed $optionProvider = null;

    public function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public static function select(string $key): self
    {
        $filter = new self($key);
        $filter->type = 'select';

        return $filter;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function optionProvider(callable $callback): self
    {
        $this->optionProvider = $callback;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label ?: ucwords(str_replace(['_', '.'], ' ', $this->key));
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOptions(): array
    {
        if ($this->optionProvider) {
            return call_user_func($this->optionProvider);
        }

        return $this->options;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->getLabel(),
            'type' => $this->type,
            'options' => $this->getOptions(),
        ];
    }
}
