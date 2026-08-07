<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `GET /devices/{device_id}/login` to request a QR code for
 * linking. Returns the provider's raw `results` payload (a QR image
 * link/data) as-is for the frontend to render.
 */
class RequestQrLogin
{
    public function handle(WaInstance $instance, string $deviceId): mixed
    {
        $response = GowaHttp::client($instance)->get("/devices/{$deviceId}/login");

        $response->throw();

        return $response->json('results');
    }
}
