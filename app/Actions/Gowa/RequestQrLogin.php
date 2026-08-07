<?php

namespace App\Actions\Gowa;

use App\Models\WaInstance;
use App\Support\WhatsApp\GowaHttp;

/**
 * Calls GoWA's `GET /devices/{device_id}/login` to request a QR code for
 * linking. `results` is `{device_id, qr_duration, qr_link}` (verified
 * directly against a running GoWA v9.0.0 instance — not the bare
 * link/data string this action originally assumed), and `qr_link`'s
 * host:port can't be trusted: GoWA builds it from the inbound request,
 * which arrives through Caddy's internal `/w/<id>` path-stripping proxy
 * (see wakuwaku-provisioner/DEPLOYMENT_PLAN.md's architecture-correction
 * notes), so it comes back missing both the port and the `/w/<id>` prefix
 * and is never reachable from anywhere, let alone the end user's browser.
 * Only `qr_link`'s path is usable; re-fetch that path through the same
 * authenticated instance client and hand the frontend a `data:` URI it can
 * render directly (`resources/js/.../link-number-dialog.tsx` already
 * special-cases `data:image` strings).
 */
class RequestQrLogin
{
    public function handle(WaInstance $instance, string $deviceId): ?string
    {
        $response = GowaHttp::client($instance)->get("/devices/{$deviceId}/login");
        $response->throw();

        $qrLink = $response->json('results.qr_link');
        if (! $qrLink) {
            return null;
        }

        $imageResponse = GowaHttp::client($instance)->get((string) parse_url($qrLink, PHP_URL_PATH));
        $imageResponse->throw();

        $mime = $imageResponse->header('Content-Type') ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($imageResponse->body());
    }
}
