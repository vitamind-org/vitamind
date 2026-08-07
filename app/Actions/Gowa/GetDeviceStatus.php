<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `GET /devices/{device_id}/status`. Returns the raw
 * `results` payload (`is_connected`, `is_logged_in`, `device_id`, `jid`).
 */
class GetDeviceStatus
{
    public function handle(WaInstance $instance, string $deviceId): array
    {
        $response = GowaHttp::client($instance)->get("/devices/{$deviceId}/status");

        $response->throw();

        return $response->json('results') ?? [];
    }
}
