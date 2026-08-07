<?php

namespace App\Actions\WhatsApp;

use App\Actions\Gowa\LogoutDevice;
use App\Enums\WaNumberStatus;
use App\Models\WaNumber;

/**
 * Unlink (specs/whatsapp-number-management/spec.md): logs the device out
 * of WhatsApp, clearing its session, while keeping the device slot and the
 * number's record so it can be relinked under the same identity.
 */
class UnlinkNumber
{
    public function handle(WaNumber $number): WaNumber
    {
        app(LogoutDevice::class)->handle($number->instance, $number->device_id);

        $number->update(['status' => WaNumberStatus::Disconnected]);

        return $number;
    }
}
