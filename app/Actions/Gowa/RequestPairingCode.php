<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `POST /devices/{device_id}/login/code?phone=...` to request
 * a pairing code for linking, as an alternative to QR scanning. `results`
 * is `{device_id, pair_code}` (verified directly against a running GoWA
 * v9.0.0 instance — not the bare code string this action originally
 * assumed); only `pair_code` is what the frontend renders.
 */
class RequestPairingCode
{
    public function handle(WaInstance $instance, string $deviceId, string $phone): ?string
    {
        $response = GowaHttp::client($instance)
            ->withOptions(['query' => ['phone' => $phone]])
            ->post("/devices/{$deviceId}/login/code");

        $response->throw();

        return $response->json('results.pair_code');
    }
}
