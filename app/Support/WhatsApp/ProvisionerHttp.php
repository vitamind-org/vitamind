<?php

namespace App\Support\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for `waini-provisioner`'s control-plane API. Verified
 * directly against the `wakuwaku-provisioner` repo's actual implementation
 * (internal/provisioner/{server,caddy}.go): Kong has been retired there —
 * Caddy is the sole edge component, and `/provisioner/*` is gated by
 * Caddy's own `basicauth` directive (HTTP Basic Auth) plus a `remote_ip`
 * matcher, not Kong's key-auth. `base_url` must include the `/provisioner`
 * path prefix Caddy strips before proxying (e.g.
 * `https://wagw.nugrahadi.com/provisioner`).
 */
class ProvisionerHttp
{
    public static function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.waini_provisioner.base_url'), '/'))
            ->withBasicAuth(
                (string) config('services.waini_provisioner.username'),
                (string) config('services.waini_provisioner.password'),
            )
            ->acceptJson()
            ->timeout(30);
    }
}
