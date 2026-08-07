<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // `waini-provisioner`'s control-plane API (create/status/delete a
    // workspace's GoWA instance), fronted by Caddy's basicauth + remote_ip
    // matcher (wakuwaku-provisioner's internal/provisioner/caddy.go) —
    // base_url must include the `/provisioner` path prefix Caddy strips
    // before proxying, e.g. https://wagw.nugrahadi.com/provisioner.
    'waini_provisioner' => [
        'base_url' => env('WAINI_PROVISIONER_BASE_URL'),
        'username' => env('WAINI_PROVISIONER_USERNAME'),
        'password' => env('WAINI_PROVISIONER_PASSWORD'),
    ],

];
