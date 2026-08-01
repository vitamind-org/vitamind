<?php

namespace VitaminD\Core\Actions\Plugins\Github;

use VitaminD\Core\Models\Plugin;
use Exception;

final readonly class UpdateGithubPlugin
{
    public function __construct(
        private InstallGithubPlugin $installPlugin,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Plugin $plugin): void
    {
        $this->installPlugin->handle($plugin->repo, $plugin);
    }
}
