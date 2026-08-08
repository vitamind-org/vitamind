<?php

namespace VitaminD\Plugins\Realtime\Tests\Feature;

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use VitaminD\Plugins\Realtime\Events\WorkspacePing;

/**
 * design.md's D5: this package's infra must not favor ShouldBroadcastNow
 * (sync) over queued ShouldBroadcast, or vice versa — that choice belongs to
 * the implementor, per event (task 5.3). WorkspacePing (the shipped
 * example) already proves the sync path end to end via
 * ChannelAuthorizationTest; this proves a queued event dispatched through
 * the same infra reaches the queue rather than being silently broadcast
 * inline or dropped.
 */
class BroadcastTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_broadcast_event_is_pushed_to_the_queue(): void
    {
        Queue::fake();

        broadcast(new class implements ShouldBroadcast
        {
            use Dispatchable, InteractsWithSockets;

            public function broadcastOn(): array
            {
                return [new Channel('example-queued')];
            }
        });

        Queue::assertPushed(BroadcastEvent::class);
    }

    public function test_should_broadcast_now_event_bypasses_the_queue(): void
    {
        Queue::fake();

        broadcast(new WorkspacePing(1, 'hello'));

        Queue::assertNothingPushed();
    }
}
