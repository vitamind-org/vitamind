<?php

namespace VitaminD\Plugins\Realtime\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on a public channel, not a workspace-scoped private one: the
 * bootstrap config it signals a change to (`GetBootstrap`'s
 * server_provider/dns_provider/plugins.views) is app-wide, not tenant-scoped,
 * so forcing it through private-channel authorization would misrepresent
 * what the data actually is (see design.md's D6). `broadcastAs()` matches
 * the `bootstrap.invalidated` type the frontend already listens for.
 */
class BootstrapInvalidated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function broadcastOn(): array
    {
        return [new Channel('bootstrap')];
    }

    public function broadcastAs(): string
    {
        return 'bootstrap.invalidated';
    }
}
