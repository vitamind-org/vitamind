<?php

namespace VitaminD\Plugins\TodoPlugin;

use VitaminD\Plugins\TodoPlugin\Models\Todo;
use VitaminD\PluginSdk\AbstractPlugin;
use VitaminD\PluginSdk\Column;
use VitaminD\PluginSdk\DTOs\DynamicField;
use VitaminD\PluginSdk\DTOs\DynamicForm;
use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\PluginSdk\RegisterRole;

class Plugin extends AbstractPlugin
{
    /**
     * The identity declared below, in `pluginDetails()` — exposed as a
     * constant so `pluginDetails()` itself, and `managerRoleKey()` below,
     * don't duplicate the literal string this plugin's identity actually is.
     */
    public const KEY = 'todo-plugin';

    /**
     * The short key `registerRoles()` registers under `self::KEY` — kept as
     * a single constant so `registerRoles()` and `managerRoleKey()` can't
     * drift apart.
     */
    private const ROLE_MANAGER = 'manager';

    /**
     * @return array{key: string, name: string, description: string}
     */
    public function pluginDetails(): array
    {
        return [
            'key' => self::KEY,
            'name' => 'Todo Plugin',
            'description' => 'Demo plugin used to verify GitHub and Composer plugin installation.',
        ];
    }

    public function boot(): void
    {
        if (app()->runningInConsole()) {
            app('migrator')->path(__DIR__.'/../database/migrations');
        }

        $this->registerRoles();

        $form = DynamicForm::make([
            DynamicField::make('title')
                ->text()
                ->label('Title')
                ->placeholder('e.g. Write the release notes')
                ->rules(['required', 'string', 'max:255']),
            DynamicField::make('is_done')
                ->checkbox()
                ->label('Done')
                ->default(false)
                ->rules(['required', 'boolean']),
        ]);

        RegisterPage::make('todo-plugin')
            ->title('Todo Plugin')
            ->icon('check-square')
            ->tabs([
                'todos' => RegisterDataTable::make('todos')
                    ->model(Todo::class)
                    ->columns([
                        Column::text('title')->label('Title')->sortable()->searchable(),
                        Column::badge('is_done', [1 => 'Done', 0 => 'Pending'])->label('Status'),
                    ])
                    ->form($form),
            ])
            ->adminOnly(false)
            ->register();
    }

    /**
     * Demonstrates the shared `registerRole()` helper (from
     * `VitaminD\PluginSdk\Concerns\RegistersOwnRole`, used by both
     * `AbstractPlugin` and `PluginBase`): a plugin declares its own role in
     * code, from its own `boot()`, scoped automatically to this class's own
     * declared identity (`self::KEY`, `todo-plugin`) — no manual
     * namespace/prefix bookkeeping. Resulting registry key:
     * `todo-plugin.manager` — see `managerRoleKey()` below (built from
     * `RegisterRole::keyFor()`, not a duplicated literal) and
     * `Todo::booted()` for where it's checked, and
     * docs/plugin-development/role-registration.md (in the main VitaminD
     * repo) for the full mechanism.
     */
    protected function registerRoles(): void
    {
        $this->registerRole(self::ROLE_MANAGER, 'Todo Manager');
    }

    /**
     * The full registry key for the role `registerRoles()` registers — for
     * other classes in this package (e.g. `Todo`) to reference without
     * hand-splicing `self::KEY.'.manager'` or re-typing the short key.
     */
    public static function managerRoleKey(): string
    {
        return RegisterRole::keyFor(self::KEY, self::ROLE_MANAGER);
    }
}
