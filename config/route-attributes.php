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

// realpath() is required here: in local development `vendor/vitamind/*` is a
// symlink to `dev-packages/*` (path repository), and the route registrar
// matches file paths against this base path using SplFileInfo::getRealPath(),
// which resolves symlinks. A non-resolved path would never match, silently
// dropping every controller in the directory from route discovery.
$corePath = realpath(base_path('vendor/vitamind/core/src/Http/Controllers'));
$coreApiPath = realpath(base_path('vendor/vitamind/core/src/Http/Controllers/API'));
$workspacePath = realpath(base_path('vendor/vitamind/workspace-plugin/src/Http/Controllers'));

if ($corePath) {
    $directories[$corePath] = [
        'namespace' => 'VitaminD\\Core\\Http\\Controllers',
        'prefix' => '',
        'middleware' => 'web',
        'patterns' => ['*Controller.php'],
        'not_patterns' => ['API/*'],
    ];
}

if ($coreApiPath) {
    $directories[$coreApiPath] = [
        'namespace' => 'VitaminD\\Core\\Http\\Controllers\\API',
        'prefix' => '',
        'middleware' => 'api',
        'patterns' => ['*Controller.php'],
        'not_patterns' => [],
    ];
}

// Workspace plugin controllers are only routable when the feature is enabled,
// keeping non-workspace apps free of workspace routes entirely.
if ($workspacePath && env('VITAMIND_FEATURE_WORKSPACES', false)) {
    $directories[$workspacePath] = [
        'namespace' => 'VitaminD\\Plugins\\Workspace\\Http\\Controllers',
        'prefix' => '',
        'middleware' => 'web',
        'patterns' => ['*Controller.php'],
        'not_patterns' => [],
    ];
}

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
