<?php

namespace App\Actions\Provisioner;

use App\Exceptions\WhatsApp\ProvisionerException;
use App\Models\WaInstance;
use App\Support\WhatsApp\ProvisionerHttp;

/**
 * Calls `waini-provisioner`'s `DELETE /instances/{workspace_id}`, which
 * stops/disables the systemd unit and removes the Caddy route in one
 * operation (specs/whatsapp-workspace-provisioning/spec.md). Returns 204 on
 * success, 404 if already absent (treated as success here too — idempotent
 * delete). Removes the local `wa_instances` row only after the remote
 * delete succeeds, so a failed delete leaves WakuWaku's record intact
 * rather than orphaning it from a still-running instance.
 */
class DeleteInstance
{
    public function handle(WaInstance $instance): void
    {
        $response = ProvisionerHttp::client()->delete("/instances/{$instance->workspace_id}");

        if ($response->failed() && $response->status() !== 404) {
            throw new ProvisionerException(
                "Failed to delete instance for workspace {$instance->workspace_id}: HTTP {$response->status()}",
                $response->status(),
                $response->json(),
            );
        }

        $instance->delete();
    }
}
