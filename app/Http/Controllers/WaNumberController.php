<?php

namespace App\Http\Controllers;

use App\Actions\Gowa\RequestPairingCode;
use App\Actions\Gowa\RequestQrLogin;
use App\Actions\WhatsApp\AddNumber;
use App\Actions\WhatsApp\DeleteNumber;
use App\Actions\WhatsApp\RefreshNumberStatus;
use App\Actions\WhatsApp\UnlinkNumber;
use App\Http\Resources\WaNumberResource;
use App\Models\WaNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * `WaNumber` is scoped to the current workspace via
 * `VitaminD\PluginSdk\Concerns\BelongsToWorkspace`, whose global scope
 * applies to implicit route-model binding too — a number belonging to a
 * different workspace 404s before any action method runs, satisfying
 * specs/whatsapp-number-management/spec.md's "Numbers are scoped to their
 * owning workspace" requirement without an extra authorization check here.
 */
#[Prefix('settings/whatsapp-numbers')]
#[Middleware(['auth'])]
class WaNumberController extends Controller
{
    #[Get('/', name: 'whatsapp-numbers')]
    public function index(): Response
    {
        return Inertia::render('whatsapp-numbers/index', [
            'numbers' => WaNumberResource::collection(WaNumber::query()->latest()->get()),
        ]);
    }

    #[Post('/', name: 'whatsapp-numbers.store')]
    public function store(): RedirectResponse
    {
        app(AddNumber::class)->handle(user()->currentWorkspace);

        return redirect()->route('whatsapp-numbers')->with('success', __('WhatsApp number added.'));
    }

    #[Get('/{waNumber}/qr', name: 'whatsapp-numbers.qr')]
    public function qr(WaNumber $waNumber): JsonResponse
    {
        return response()->json(['qr' => app(RequestQrLogin::class)->handle($waNumber->instance, $waNumber->device_id)]);
    }

    #[Post('/{waNumber}/pairing-code', name: 'whatsapp-numbers.pairing-code')]
    public function pairingCode(WaNumber $waNumber, Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string']]);

        $code = app(RequestPairingCode::class)->handle($waNumber->instance, $waNumber->device_id, $request->string('phone')->value());

        $waNumber->update(['phone_number' => $request->string('phone')->value()]);

        return response()->json(['code' => $code]);
    }

    #[Get('/{waNumber}/status', name: 'whatsapp-numbers.status')]
    public function status(WaNumber $waNumber): JsonResponse
    {
        $waNumber = app(RefreshNumberStatus::class)->handle($waNumber);

        return response()->json(WaNumberResource::make($waNumber));
    }

    #[Post('/{waNumber}/unlink', name: 'whatsapp-numbers.unlink')]
    public function unlink(WaNumber $waNumber): RedirectResponse
    {
        app(UnlinkNumber::class)->handle($waNumber);

        return redirect()->route('whatsapp-numbers')->with('success', __('WhatsApp number unlinked.'));
    }

    #[Delete('/{waNumber}', name: 'whatsapp-numbers.destroy')]
    public function destroy(WaNumber $waNumber): RedirectResponse
    {
        app(DeleteNumber::class)->handle($waNumber);

        return redirect()->route('whatsapp-numbers')->with('success', __('WhatsApp number deleted.'));
    }
}
