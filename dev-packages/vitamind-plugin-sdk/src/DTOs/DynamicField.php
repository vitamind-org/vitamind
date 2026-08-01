<?php

namespace VitaminD\PluginSdk\DTOs;

use Spatie\LaravelData\Data;

/**
 * @typescript
 */
class DynamicField extends Data
{
    public function __construct(
        public string $name,
        public string $type = 'text',
        public string $label = '',
        public mixed $default = null,
        public ?string $placeholder = null,
        public ?string $description = null,
        public ?array $options = null,
        public ?array $optionLabels = null,
        public ?array $link = null,
        public ?string $className = null,
        public ?array $componentProps = null,
        public ?array $rules = null,
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function component(): self
    {
        $this->type = 'component';

        return $this;
    }

    public function text(): self
    {
        $this->type = 'text';

        return $this;
    }

    public function number(): self
    {
        $this->type = 'number';

        return $this;
    }

    public function password(): self
    {
        $this->type = 'password';

        return $this;
    }

    public function passwordWithToggle(): self
    {
        $this->type = 'password-with-toggle';

        return $this;
    }

    public function textarea(): self
    {
        $this->type = 'textarea';

        return $this;
    }

    public function select(): self
    {
        $this->type = 'select';

        return $this;
    }

    public function checkbox(): self
    {
        $this->type = 'checkbox';

        return $this;
    }

    public function switch(): self
    {
        $this->type = 'checkbox'; // Map switch to checkbox type on frontend

        return $this;
    }

    public function alert(): self
    {
        $this->type = 'alert';

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function placeholder(?string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function options(?array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->link = [
            'label' => $label,
            'url' => $url,
        ];

        return $this;
    }

    public function className(?string $className): self
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public function componentProps(array $props): self
    {
        $this->componentProps = $props;

        return $this;
    }

    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label,
            'default' => $this->default,
            'placeholder' => $this->placeholder,
            'description' => $this->description,
            'options' => $this->options,
            'optionLabels' => $this->optionLabels,
            'link' => $this->link,
            'className' => $this->className,
            'componentProps' => $this->componentProps,
            'rules' => $this->rules,
        ];
    }
}
