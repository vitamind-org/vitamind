<?php

namespace App\Actions\Provisioner;

use App\Enums\WaInstanceState;
use App\Exceptions\WhatsApp\ProvisionerException;
use App\Models\WaInstance;
use App\Support\WhatsApp\ProvisionerHttp;
use Illuminate\Support\Str;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Calls `waini-provisioner`'s `POST /instances`. Idempotent per
 * specs/whatsapp-workspace-provisioning/spec.md — safe to call again for a
 * workspace that already has an instance; the provisioner returns that
 * instance's existing details unchanged rather than reallocating anything.
 *
 * Response shape verified directly against `wakuwaku-provisioner`'s actual
 * implementation (internal/provisioner/server.go's `instanceResponse`):
 * `{"workspace_id", "status", "base_url", "port", "basic_auth_username",
 * "basic_auth_password"}` — flat fields, not a nested `basic_auth` object,
 * and `status` not `state`.
 *
 * The provisioner has no concept of a GoWA webhook secret at all (it never
 * configures GoWA's `WHATSAPP_WEBHOOK_SECRET`) — WakuWaku generates and
 * owns this secret itself, passing it to every device created on the
 * instance via App\Actions\Gowa\AddDevice's per-device `webhook_secret`
 * (GoWA's `POST /devices` accepts one). Generated once per instance and
 * preserved across idempotent re-calls, not re-derived from the response.
 */
class CreateInstance
{
    public function handle(Workspace $workspace): WaInstance
    {
        $response = ProvisionerHttp::client()->post('/instances', [
            'workspace_id' => (string) $workspace->id,
        ]);

        if ($response->failed()) {
            throw new ProvisionerException(
                "Failed to provision instance for workspace {$workspace->id}: HTTP {$response->status()}",
                $response->status(),
                $response->json(),
            );
        }

        $data = $response->json();
        $existing = WaInstance::firstWhere('workspace_id', $workspace->id);

        return WaInstance::updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'base_url' => $data['base_url'],
                'basic_auth_username' => $data['basic_auth_username'],
                'basic_auth_password' => $data['basic_auth_password'],
                'webhook_secret' => $existing?->webhook_secret ?? Str::random(40),
                'state' => WaInstanceState::from($data['status']),
            ]
        );
    }
}
