<?php

namespace App\Plugins\HelloWorld;

use Illuminate\Support\Facades\Log;
use VitaminD\PluginSdk\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Hello World';

    protected string $description = 'A very simple HelloWorld demo local plugin for VitaminD.';

    public function boot(): void
    {
        // Log::info('Hello World plugin booted!');
    }

    public function enable(): void
    {
        Log::info('Hello World plugin enabled!');
    }

    public function disable(): void
    {
        Log::info('Hello World plugin disabled!');
    }

    public function install(): void
    {
        Log::info('Hello World plugin installed!');
    }

    public function uninstall(): void
    {
        Log::info('Hello World plugin uninstalled!');
    }
}
