<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `POST /devices/{device_id}/login/code?phone=...` to request
 * a pairing code for linking, as an alternative to QR scanning.
 */
class RequestPairingCode
{
    public function handle(WaInstance $instance, string $deviceId, string $phone): mixed
    {
        $response = GowaHttp::client($instance)
            ->withOptions(['query' => ['phone' => $phone]])
            ->post("/devices/{$deviceId}/login/code");

        $response->throw();

        return $response->json('results');
    }
}
