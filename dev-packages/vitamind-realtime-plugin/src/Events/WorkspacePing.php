<?php

namespace VitaminD\Plugins\Realtime\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Worked example proving the Layer 2 workspace-authorization helper
 * end-to-end (design.md's "prove the pattern with one real end-to-end
 * example" goal) — a template for implementors' own workspace-scoped
 * events, not something apps are expected to dispatch themselves. Its
 * channel is registered in RealtimeServiceProvider::registerChannels(),
 * authorized via WorkspaceChannelAuthorization.
 */
class WorkspacePing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $workspaceId,
        public readonly string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->workspaceId}.ping")];
    }

    public function broadcastAs(): string
    {
        return 'ping';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
