<?php

namespace Tests\Feature;

use App\Enums\WaInstanceState;
use App\Enums\WaNumberStatus;
use App\Models\User;
use App\Models\WaInstance;
use App\Models\WaNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use VitaminD\Plugins\Workspace\Actions\Workspaces\CreateWorkspace;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function numberWithSecret(string $secret): WaNumber
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'acme-corp-'.Str::random(8)]);

        $instance = WaInstance::create([
            'workspace_id' => $workspace->id,
            'base_url' => 'https://waini.example.com/w/acme',
            'basic_auth_username' => 'wa',
            'basic_auth_password' => 'secret',
            'webhook_secret' => $secret,
            'state' => WaInstanceState::Active,
        ]);

        $this->actingAs($owner);

        return WaNumber::create([
            'wa_instance_id' => $instance->id,
            'device_id' => 'device-'.Str::random(8),
            'status' => WaNumberStatus::Connected,
        ]);
    }

    public function test_webhook_accepted_with_a_valid_signature(): void
    {
        $number = $this->numberWithSecret('whsec_test');

        $payload = json_encode([
            'event' => 'message.ack',
            'session_id' => $number->device_id,
            'payload' => [],
        ]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'whsec_test');

        $response = $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
    }

    public function test_webhook_rejected_with_an_invalid_signature(): void
    {
        $number = $this->numberWithSecret('whsec_test');

        $payload = json_encode([
            'event' => 'message.ack',
            'session_id' => $number->device_id,
            'payload' => [],
        ]);

        $response = $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => 'sha256=not-the-right-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertUnauthorized();
    }

    public function test_webhook_for_unknown_session_id_is_acknowledged_and_ignored(): void
    {
        $response = $this->postJson('/api/webhooks/whatsapp', [
            'event' => 'message.ack',
            'session_id' => 'does-not-exist',
            'payload' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ignored');
    }

    public function test_webhook_event_for_one_workspace_is_not_attributed_to_another(): void
    {
        $this->numberWithSecret('secret-a');
        $numberB = $this->numberWithSecret('secret-b');

        $payload = json_encode([
            'event' => 'message.ack',
            'session_id' => $numberB->device_id,
            'payload' => [],
        ]);
        // Signed with workspace A's secret, but addressed to workspace B's
        // session_id — must not be accepted as a valid B event.
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'secret-a');

        $response = $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertUnauthorized();
    }
}
