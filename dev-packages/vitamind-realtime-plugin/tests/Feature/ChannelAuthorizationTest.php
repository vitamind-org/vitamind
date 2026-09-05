<?php

namespace VitaminD\Plugins\Realtime\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use VitaminD\Plugins\Realtime\Support\WorkspaceChannelAuthorization;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Verifies the real /broadcasting/auth endpoint end to end (tasks 2.4 and
 * 5.2) — the shipped example channel from RealtimeServiceProvider, hit over
 * HTTP exactly like Echo/Reverb would.
 *
 * The shared phpunit.xml defaults BROADCAST_CONNECTION to `null` so the
 * rest of the suite never risks a real network call from a
 * ShouldBroadcastNow event. NullBroadcaster::auth() is a no-op — it never
 * calls the registered authorization callback at all — so this test
 * switches the default connection to `reverb` itself and re-registers the
 * channel against it: RealtimeServiceProvider::boot() already registered it
 * once at application-boot time, but against whichever driver was default
 * then (`null`), and BroadcastManager caches resolved driver instances per
 * connection name, so a driver resolved for the first time here starts with
 * an empty channel list unless it's registered again.
 */
class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
        ]);

        Broadcast::channel('workspace.{workspaceId}.ping', function ($user, $workspaceId): bool {
            return WorkspaceChannelAuthorization::check($user, $workspaceId);
        }, ['guards' => ['web', 'sanctum']]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $workspace = Workspace::create(['name' => 'acme']);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-workspace.{$workspace->id}.ping",
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    public function test_workspace_member_is_authorized(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => "private-workspace.{$workspace->id}.ping",
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }

    public function test_non_member_is_denied(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => "private-workspace.{$workspace->id}.ping",
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    /**
     * Without the `guards => ['web', 'sanctum']` option on the channel
     * registration, Laravel only tries the app's default (`web`, session)
     * guard, so a request authenticated purely via a Sanctum bearer token —
     * no session cookie at all — would never reach the authorization
     * callback above and would 403 even for a genuine member.
     */
    public function test_workspace_member_is_authorized_via_sanctum_bearer_token(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'acme']);
        $workspace->users()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])->postJson('/broadcasting/auth', [
            'channel_name' => "private-workspace.{$workspace->id}.ping",
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }
}
