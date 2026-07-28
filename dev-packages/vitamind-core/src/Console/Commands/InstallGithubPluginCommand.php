<?php

namespace VitaminD\Core\Console\Commands;

use VitaminD\Core\Actions\Plugins\InstallPluginFromGithub;
use Illuminate\Console\Command;
use Throwable;

class InstallGithubPluginCommand extends Command
{
    protected $signature = 'plugin:install-github {repository : GitHub repository, e.g. vitamind-org/plugin-example} {--branch= : Branch or tag to clone instead of the default branch}';

    protected $description = 'Install a plugin by cloning a GitHub repository into storage/plugins';

    public function handle(InstallPluginFromGithub $action): int
    {
        try {
            $plugin = $action->handle($this->argument('repository'), $this->option('branch'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Plugin '{$plugin->folder}' installed from {$this->argument('repository')}.");

        return self::SUCCESS;
    }
}
