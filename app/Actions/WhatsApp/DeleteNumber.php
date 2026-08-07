<?php

namespace App\Actions\WhatsApp;

use App\Actions\Gowa\DeleteDevice;
use App\Models\WaNumber;

/**
 * Delete (specs/whatsapp-number-management/spec.md): removes the device
 * entirely from the workspace's GoWA instance — session and chat data
 * included — as well as the number's own record, distinct from unlinking.
 */
class DeleteNumber
{
    public function handle(WaNumber $number): void
    {
        app(DeleteDevice::class)->handle($number->instance, $number->device_id);

        $number->delete();
    }
}
