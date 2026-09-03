<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk archive-plugin stores uploaded files on. This disk MUST NOT
    | have `serve => true` or `visibility => 'public'` set (see this
    | project's config/filesystems.php) — either would make uploaded files
    | reachable by a guessed or constructed URL, bypassing the per-request
    | policy check every download in this plugin is built around. The
    | plugin only ever reads this disk via
    | Storage::disk(...)->response()/delete() — never Storage::url()/
    | temporaryUrl() — so keep that same guarantee if you point this at a
    | different disk.
    |
    */

    'disk' => env('ARCHIVE_PLUGIN_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Explicit allow-list for uploads. Enforced in addition to — not
    | instead of — a server-side content/MIME cross-check against the
    | claimed extension.
    |
    */

    'allowed_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'txt', 'csv',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | In kilobytes, passed directly to the `max` validation rule.
    |
    */

    'max_upload_size' => (int) env('ARCHIVE_PLUGIN_MAX_UPLOAD_SIZE', 20 * 1024),

];
