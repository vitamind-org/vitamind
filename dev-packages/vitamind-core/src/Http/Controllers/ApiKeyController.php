<?php

namespace VitaminD\Core\Http\Controllers;

use VitaminD\Core\Actions\ApiKey\CreateApiKey;
use VitaminD\Core\Http\Resources\ApiKeyResource;
use VitaminD\Core\Http\Resources\WorkspaceResource;
use VitaminD\Core\Models\PersonalAccessToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/api-keys')]
#[Middleware(['auth'])]
class ApiKeyController extends Controller
{
    #[Get('/', name: 'api-keys')]
    public function index(): Response
    {
        $this->authorize('viewAny', PersonalAccessToken::class);

        $workspaces = config('vitamin-d.features.workspaces', false) 
            ? user()->workspaces()->get() 
            : collect();

        return Inertia::render('api-keys/index', [
            'apiKeys' => ApiKeyResource::collection(user()->tokens()->simplePaginate(config('web.pagination_size', 10))),
            'workspaces' => WorkspaceResource::collection($workspaces)->resolve(),
        ]);
    }

    #[Post('/', name: 'api-keys.store')]
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PersonalAccessToken::class);

        $token = app(CreateApiKey::class)->create(user(), $request->all());

        return back()
            ->with('success', 'Api key created.')
            ->with('data', [
                'token' => $token->plainTextToken,
            ]);
    }

    #[Delete('/{apiKey}', name: 'api-keys.destroy')]
    public function destroy(PersonalAccessToken $apiKey): RedirectResponse
    {
        $this->authorize('delete', $apiKey);

        $apiKey->delete();

        return back()->with('success', 'Api Key deleted.');
    }
}
