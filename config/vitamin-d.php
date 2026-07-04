<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VitaminD Feature Flags
    |--------------------------------------------------------------------------
    |
    | Toggle features on/off. Some features require additional setup
    | (migrations, service providers, etc.) when enabled.
    |
    */

    'features' => [
        // Multi-tenant workspace system (Workspace model + user ↔ workspace pivot)
        'workspaces' => (bool) env('VITAMIND_FEATURE_WORKSPACES', false),

        // REST API layer (Sanctum + API controllers)
        'api' => (bool) env('VITAMIND_FEATURE_API', true),

        // Two-Factor Authentication via Laravel Fortify
        'two_factor' => (bool) env('VITAMIND_FEATURE_2FA', true),

        // Real-time WebSocket server
        'websocket' => (bool) env('VITAMIND_FEATURE_WEBSOCKET', false),

        // Admin panel (/admin/* routes)
        'admin_panel' => (bool) env('VITAMIND_FEATURE_ADMIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination_size' => (int) env('VITAMIND_PAGINATION_SIZE', 25),
];
