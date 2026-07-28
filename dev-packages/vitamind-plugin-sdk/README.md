# VitaminD Plugin SDK

![CodeRabbit Pull Request Reviews](https://img.shields.io/coderabbit/prs/github/vitamind-org/vitamind-plugin-sdk?utm_source=oss&utm_medium=github&utm_campaign=vitamind-org%2Fvitamind-plugin-sdk&labelColor=171717&color=FF570A&link=https%3A%2F%2Fcoderabbit.ai&label=CodeRabbit+Reviews)

Core SDK contracts, DTOs, and registries for building VitaminD plugins. This package provides the foundational architecture to extend VitaminD applications with custom views, data tables, commands, and administration pages.

## Installation

You can install the package via Composer (typically loaded locally within a monorepo setup):

```bash
composer require vitamind/plugin-sdk
```

## Key Features

### 1. Abstract Plugin Lifecycle
Extend `VitaminD\PluginSdk\AbstractPlugin` to define your plugin and hook into lifecycle events:

```php
use VitaminD\PluginSdk\AbstractPlugin;

class MyPlugin extends AbstractPlugin
{
    protected string $name = 'My Plugin';
    protected string $description = 'A custom plugin example.';

    public function boot(): void
    {
        // Executed during application boot
    }

    public function enable(): void
    {
        // Executed when the plugin is enabled
    }
}
```

### 2. DataTable Registration
Define columns, filters, and dynamic forms for custom administration tables using `RegisterDataTable`:

```php
use VitaminD\PluginSdk\RegisterDataTable;
use VitaminD\PluginSdk\Column;
use VitaminD\PluginSdk\Filter;

$table = RegisterDataTable::make('products_table')
    ->model(Product::class)
    ->columns([
        Column::text('name')->label('Product Name')->searchable(),
        Column::badge('status', ['active' => 'Active', 'inactive' => 'Inactive']),
    ])
    ->filters([
        Filter::select('status')->label('Status Filter')->options([
            'active' => 'Active',
            'inactive' => 'Inactive',
        ]),
    ]);
```

### 3. Custom Page Registration
Register pages in the administration panel under tabs or side navigation:

```php
use VitaminD\PluginSdk\RegisterPage;

RegisterPage::make('settings_page')
    ->title('Plugin Settings')
    ->icon('cog')
    ->adminOnly(true)
    ->tabs([
        'general' => $dataTable,
    ])
    ->register();
```

### 4. Console Commands & Views
Register Artisan commands and blade/inertia views:

```php
use VitaminD\PluginSdk\RegisterCommand;
use VitaminD\PluginSdk\RegisterViews;

// Register Command
RegisterCommand::make(MyCustomCommand::class)->register();

// Register Custom View Namespace
RegisterViews::make('my-plugin')->path(__DIR__ . '/../resources/views')->register();
```

## Testing

To run the unit tests, use the test runner in the host application:

```bash
php artisan test --testsuite=PluginSdk
```
