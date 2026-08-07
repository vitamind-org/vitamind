<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `POST /devices/{device_id}/logout` — logs the device out of
 * WhatsApp (clears its session) while keeping the device slot itself, so it
 * can be relinked under the same identity.
 */
class LogoutDevice
{
    public function handle(WaInstance $instance, string $deviceId): void
    {
        GowaHttp::client($instance)->post("/devices/{$deviceId}/logout")->throw();
    }
}
