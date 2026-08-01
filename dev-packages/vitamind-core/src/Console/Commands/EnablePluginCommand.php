<?php

namespace VitaminD\Core\Console\Commands;

use VitaminD\Core\Actions\Plugins\EnablePlugin;
use VitaminD\Core\Models\Plugin;
use Illuminate\Console\Command;
use Throwable;

class EnablePluginCommand extends Command
{
    protected $signature = 'plugin:enable {folder : Plugin folder name, e.g. TodoPlugin (GitHub/Local) or todo-plugin (Composer)}';

    protected $description = 'Enable an installed plugin, running its pending migrations';

    public function handle(EnablePlugin $action): int
    {
        $plugin = Plugin::where('folder', $this->argument('folder'))->first();

        if (! $plugin) {
            $this->error("No plugin found with folder '{$this->argument('folder')}'.");

            return self::FAILURE;
        }

        try {
            $action->handle($plugin);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin '{$plugin->folder}' enabled.");

        return self::SUCCESS;
    }
}
