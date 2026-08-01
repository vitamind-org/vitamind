<?php

$directories = [];

if (is_dir(app_path('Http/Controllers'))) {
    $directories[app_path('Http/Controllers')] = [
        'prefix' => '',
        'middleware' => 'web',
        'patterns' => ['*Controller.php'],
        'not_patterns' => ['API/*'],
    ];
}

if (is_dir(app_path('Http/Controllers/API'))) {
    $directories[app_path('Http/Controllers/API')] = [
        'prefix' => '',
        'middleware' => 'api',
        'patterns' => ['*Controller.php'],
        'not_patterns' => [],
    ];
}

// vitamind/core and vitamind/workspace-plugin register their own routes
// directly (in CoreServiceProvider/WorkspaceServiceProvider) rather than
// relying on this file — a fresh `composer require vitamind/core` shouldn't
// depend on the consuming app knowing the package's internal directory
// structure. This file only needs to cover the boilerplate's own app/
// controllers.
return [
    /*
     *  Automatic registration of routes will only happen if this setting is `true`
     */
    'enabled' => true,

    /*
     * Controllers in these directories that have routing attributes
     * will automatically be registered.
     *
     * Optionally, you can specify group configuration by using key/values
     */
    'directories' => $directories,

    /*
     * This middleware will be applied to all routes.
     */
    'middleware' => [
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],

    /*
     * When enabled, implicitly scoped bindings will be enabled by default.
     * You can override this behaviour by using the `ScopeBindings` attribute, and passing `false` to it.
     *
     * Possible values:
     *  - null: use the default behaviour
     *  - true: enable implicitly scoped bindings for all routes
     *  - false: disable implicitly scoped bindings for all routes
     */
    'scope-bindings' => null,
];
