<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WaNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\RouteAttributes\Attributes\Post;

/**
 * Single shared inbound webhook for every workspace's GoWA instance
 * (specs/whatsapp-number-management/spec.md §Webhook events). Unauthenticated
 * by design (GoWA calls this directly) — verified instead by an HMAC
 * signature over the raw body, checked against the owning instance's own
 * `webhook_secret` once attribution below identifies which instance sent it.
 *
 * GoWA's payload shape (`docs/webhook-payload.md`, verified against the
 * upstream repo): `{"event": "...", "device_id": "<jid>", "session_id":
 * "<our device_id>", "payload": {...}}`, signed via `X-Hub-Signature-256:
 * sha256=<hmac>`. `session_id` is the identifier WakuWaku chose when calling
 * `POST /devices` (see App\Actions\Gowa\AddDevice) — it's what's matched
 * against `wa_numbers.device_id` here, not GoWA's own `device_id` field
 * (which is the WhatsApp JID).
 *
 * TODO: remote/phone-initiated logout is NOT currently deliverable through
 * this webhook — verified directly against GoWA's source
 * (src/infrastructure/whatsapp/event_handler.go): `handleLoggedOut()` only
 * broadcasts over GoWA's internal WebSocket (`DEVICE_LOGGED_OUT`), it never
 * calls a `forwardXToWebhook()` function the way every other event type
 * does. specs/whatsapp-number-management/spec.md's "Remote unlink events are
 * reflected without a WakuWaku-initiated action" requirement is therefore
 * not implemented here — left for a future change once a delivery mechanism
 * exists (e.g. status polling, or an upstream GoWA fix).
 */
class WhatsAppWebhookController extends Controller
{
    #[Post('api/webhooks/whatsapp', name: 'webhooks.whatsapp')]
    public function __invoke(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');

        $number = WaNumber::withoutGlobalScope('workspace')
            ->where('device_id', $sessionId)
            ->first();

        if (! $number) {
            // Nothing to attribute this to and nothing to verify against —
            // acknowledge so GoWA doesn't retry a payload we can never match.
            return response()->json(['status' => 'ignored']);
        }

        if (! $this->hasValidSignature($request, $number->instance->webhook_secret)) {
            abort(401);
        }

        Log::info('WhatsApp webhook received', [
            'event' => $request->input('event'),
            'wa_number_id' => $number->id,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function hasValidSignature(Request $request, ?string $secret): bool
    {
        if (! $secret) {
            return false;
        }

        $signatureHeader = (string) $request->header('X-Hub-Signature-256');

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $provided = substr($signatureHeader, strlen('sha256='));

        return hash_equals($expected, $provided);
    }
}
