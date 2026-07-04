<?php

namespace App\Actions\Plugins;

use App\Models\PluginError;
use Throwable;

final readonly class BootPlugins
{
    public function __construct(
        private GetPluginInstance $getInstance,
        private PluginCache $cache,
    ) {}

    public function handle(): void
    {
        $plugins = $this->cache->get();
        $booted = [];

        foreach ($plugins as $plugin) {
            try {
                $instance = $this->getInstance->handle($plugin);
                if ($instance) {
                    $instance->boot();
                    $booted[] = $plugin;
                }
            } catch (Throwable $exception) {
                $plugin->is_enabled = false;
                $plugin->save();
                PluginError::createFromException($exception, $plugin, true);
            }
        }

        // Where we have booted fewer plugins than were loaded
        // collect the plugins and set the cache for next time
        if (count($booted) < count($plugins)) {
            $this->cache->set(collect($booted));
        }
    }
}
