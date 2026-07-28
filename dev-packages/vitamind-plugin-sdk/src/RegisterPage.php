<?php

namespace VitaminD\PluginSdk;

class RegisterPage
{

    private string $title = '';
    private string $icon = 'package';
    private bool $adminOnly = true;
    private array $tabs = [];

    public function __construct(
        private readonly string $key,
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    private static array $registry = [];

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function adminOnly(bool $adminOnly = true): self
    {
        $this->adminOnly = $adminOnly;

        return $this;
    }

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    public function tabs(array $tabs): self
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function getTabs(): array
    {
        return $this->tabs;
    }

    public function register(): void
    {
        self::$registry[$this->key] = $this;
    }

    public static function get(): array
    {
        return self::$registry;
    }

    public static function find(string $key): ?self
    {
        return self::$registry[$key] ?? null;
    }

    public function toArray(): array
    {
        $tabsData = [];
        foreach ($this->tabs as $tabKey => $table) {
            $tabsData[$tabKey] = $table->toArray();
        }

        return [
            'key' => $this->key,
            'title' => $this->title,
            'icon' => $this->icon,
            'admin_only' => $this->adminOnly,
            'tabs' => $tabsData,
        ];
    }
}
