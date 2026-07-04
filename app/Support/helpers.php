<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (! function_exists('date_with_timezone')) {
    /**
     * @throws Exception
     */
    function date_with_timezone(mixed $date, string $timezone): string
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));
        $time = strtotime((string) $date);
        if ($time === false) {
            throw new Exception('Invalid date');
        }
        $dt->setTimestamp($time);

        return $dt->format('Y-m-d H:i:s');
    }
}

if (! function_exists('user')) {
    function user(): ?User
    {
        /** @var ?User $user */
        $user = Auth::user();

        return $user;
    }
}

if (! function_exists('plugins_path')) {
    function plugins_path(?string $path = null): string
    {
        if ($path === null) {
            $path = storage_path('plugins');
            if (! file_exists($path)) {
                mkdir($path, 0755, true);
            }

            return $path;
        }

        return storage_path('plugins'.'/'.$path);
    }
}

if (! function_exists('composer_path')) {
    function composer_path(): ?string
    {
        $paths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/opt/homebrew/bin/composer',
            trim((string) shell_exec('which composer')),
        ];

        return array_find($paths, fn ($path) => is_executable($path));
    }
}

if (! function_exists('php_path')) {
    function php_path(): ?string
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? Illuminate\Support\php_binary()
            : (new Symfony\Component\Process\PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? Illuminate\Support\ProcessUtils::escapeArgument($phpBinary)
            : null;
    }
}

if (! function_exists('git_path')) {
    function git_path(): ?string
    {
        $paths = [
            '/usr/local/bin/git',
            '/usr/bin/git',
            '/opt/homebrew/bin/git',
            trim((string) shell_exec('which git')),
        ];

        return array_find($paths, fn ($path) => is_executable($path));
    }
}
