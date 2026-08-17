<?php

namespace VitaminD\Plugins\Realtime\Tests\Feature;

use App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use VitaminD\Plugins\Realtime\Events\WorkspacePing;
use VitaminD\Plugins\Realtime\Support\WorkspaceChannelAuthorization;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Closes the gap spec.md's "one proven end-to-end example" requirement left
 * open: ChannelAuthorizationTest proves the auth callback grants/denies a
 * subscription request, and BroadcastTimingTest proves sync-vs-queued
 * dispatch, but neither asserts what actually gets sent to Reverb for an
 * authorized subscriber's channel. The `reverb` driver is Laravel's
 * PusherBroadcaster underneath (Reverb speaks the Pusher wire protocol), so
 * this intercepts its outgoing HTTP trigger call via a Guzzle MockHandler
 * injected through `client_options` — provable without a live
 * `php artisan reverb:start` process. See README.md's "Local development"
 * for the manual live-server check (this PR's own test plan) that this
 * complements rather than replaces.
 */
class BroadcastDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_subscribers_channel_receives_the_broadcast_payload(): void
    {
        // Same membership shape ChannelAuthorizationTest proves is granted
        // access to this exact channel — this test picks up from there and
        // asserts what Reverb is actually told to deliver on it.
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id, 'role' => 'owner']);

        $requests = [];
        $mockHandler = new MockHandler([new Response(200, [], '{}')]);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($requests));

        Config::set('broadcasting.default', 'reverb');
        Config::set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
            'client_options' => ['handler' => $handlerStack],
        ]);

        Broadcast::channel('workspace.{workspaceId}.ping', function ($authUser, $workspaceId): bool {
            return WorkspaceChannelAuthorization::check($authUser, $workspaceId);
        }, ['guards' => ['web', 'sanctum']]);

        broadcast(new WorkspacePing($workspace->id, 'hello'));

        $this->assertCount(1, $requests, 'Expected exactly one trigger call to the Reverb HTTP API.');

        $body = json_decode((string) $requests[0]['request']->getBody(), true);

        $this->assertSame('ping', $body['name']);
        $this->assertSame("private-workspace.{$workspace->id}.ping", $body['channel']);
        $this->assertSame(['message' => 'hello'], json_decode($body['data'], true));
    }
}
