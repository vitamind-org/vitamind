<?php

namespace App\Actions\WhatsApp;

use App\Actions\Gowa\AddDevice;
use App\Actions\Provisioner\CreateInstance;
use App\Enums\WaNumberStatus;
use App\Models\WaNumber;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * "Add number" flow (specs/whatsapp-number-management/spec.md): ensures the
 * workspace's GoWA instance exists — lazily provisioning it if this is the
 * workspace's first number — then creates a device on that instance and
 * persists the WaNumber record. Always calls CreateInstance rather than
 * checking for an existing local `wa_instances` row first: the provisioner
 * is idempotent (design.md D8), so relying on its own idempotency avoids a
 * local check-then-create race and self-heals a stale local record.
 */
class AddNumber
{
    public function handle(Workspace $workspace): WaNumber
    {
        $instance = app(CreateInstance::class)->handle($workspace);

        $deviceId = app(AddDevice::class)->handle($instance);

        return WaNumber::create([
            'wa_instance_id' => $instance->id,
            'device_id' => $deviceId,
            'status' => WaNumberStatus::Disconnected,
        ]);
    }
}
