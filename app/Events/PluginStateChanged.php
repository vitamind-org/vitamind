<?php

namespace App\Events;

use App\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PluginStateChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly string $action,
    ) {}
}
