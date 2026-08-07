<?php

namespace App\Actions\Provisioner;

use App\Enums\ProvisionerInstanceStatus;
use App\Exceptions\WhatsApp\ProvisionerException;
use App\Support\WhatsApp\ProvisionerHttp;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Calls `waini-provisioner`'s `GET /instances/{workspace_id}`. Read-only
 * per specs/whatsapp-workspace-provisioning/spec.md ("Instance status can
 * be queried independently of provisioning ... without side effects") —
 * this intentionally never writes to the local `wa_instances` row.
 *
 * Verified directly against `wakuwaku-provisioner`'s implementation
 * (internal/provisioner/manager.go's `Status()`): an absent instance is
 * **not** a 404 — the endpoint always returns HTTP 200 with
 * `{"workspace_id", "status": "active"|"unhealthy"|"absent"}`.
 */
class GetInstanceStatus
{
    public function handle(Workspace $workspace): ProvisionerInstanceStatus
    {
        $response = ProvisionerHttp::client()->get("/instances/{$workspace->id}");

        if ($response->failed()) {
            throw new ProvisionerException(
                "Failed to fetch instance status for workspace {$workspace->id}: HTTP {$response->status()}",
                $response->status(),
                $response->json(),
            );
        }

        return ProvisionerInstanceStatus::from($response->json('status'));
    }
}
