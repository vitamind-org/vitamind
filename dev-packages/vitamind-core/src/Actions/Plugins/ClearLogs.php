<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Models\Plugin;
use VitaminD\Core\Models\PluginError;

final readonly class ClearLogs
{
    public function __construct() {}

    public function handle(Plugin $plugin): void
    {
        PluginError::where('plugin_id', $plugin->id)->delete();
    }
}
