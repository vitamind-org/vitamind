<?php

namespace VitaminD\Plugins\TodoPlugin;

use VitaminD\PluginSdk\AbstractPlugin;
use VitaminD\PluginSdk\Column;
use VitaminD\PluginSdk\DTOs\DynamicField;
use VitaminD\PluginSdk\DTOs\DynamicForm;
use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\RegisterPage;
use VitaminD\Plugins\TodoPlugin\Models\Todo;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Todo Plugin';

    protected string $description = 'Demo plugin used to verify GitHub and Composer plugin installation.';

    public function boot(): void
    {
        if (app()->runningInConsole()) {
            app('migrator')->path(__DIR__.'/../database/migrations');
        }

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
}
