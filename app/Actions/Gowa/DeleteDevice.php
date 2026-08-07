<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `DELETE /devices/{device_id}` — removes the device slot
 * entirely, including its session and chat data, distinct from a logout
 * (which keeps the slot).
 */
class DeleteDevice
{
    public function handle(WaInstance $instance, string $deviceId): void
    {
        GowaHttp::client($instance)->delete("/devices/{$deviceId}")->throw();
    }
}
