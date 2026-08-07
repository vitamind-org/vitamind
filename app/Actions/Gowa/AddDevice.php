<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;
use Illuminate\Support\Str;

/**
 * Calls GoWA's `POST /devices` to create a new device slot on a workspace's
 * instance (docs/openapi.yaml in aldinokemal/go-whatsapp-web-multidevice
 * v9.0.0, verified directly against the upstream repo). Registers WakuWaku's
 * shared webhook receiver and the instance's own webhook secret at creation
 * time, so events for this device arrive signed and attributable (see
 * `whatsapp-number-management` spec §Webhook events).
 */
class AddDevice
{
    public function handle(WaInstance $instance): string
    {
        $deviceId = (string) Str::uuid();

        $response = GowaHttp::client($instance)->post('/devices', array_filter([
            'device_id' => $deviceId,
            'webhook_url' => route('webhooks.whatsapp'),
            'webhook_secret' => $instance->webhook_secret,
        ]));

        $response->throw();

        return $response->json('results.device_id') ?? $deviceId;
    }
}
