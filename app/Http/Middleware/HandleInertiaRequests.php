<?php

namespace App\Http\Middleware;

use App\Actions\Bootstrap\GetBootstrap;
use App\Http\Resources\UserResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var ?User $user */
        $user = $request->user();

        $features = config('vitamin-d.features');

        $currentWorkspace = null;
        if ($user && config('vitamin-d.features.workspaces', false)) {
            $currentWorkspace = $user->currentWorkspace;
            $canSeeCurrentWorkspace = $currentWorkspace && $user->can('view', $currentWorkspace);
            if (! $currentWorkspace || ! $canSeeCurrentWorkspace) {
                $user->ensureHasDefaultWorkspace();
                $user->unsetRelation('currentWorkspace');
                $currentWorkspace = $user->currentWorkspace;
            }
        }

        return [
            ...parent::share($request),
            'auth' => $user ? [
                'user' => UserResource::make($user->load('workspaces')),
                'currentWorkspace' => $currentWorkspace ? WorkspaceResource::make($currentWorkspace) : null,
            ] : null,
            'features' => $features,
            'csrf_token' => csrf_token(),
            'bootstrap_version' => app(GetBootstrap::class)->version(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error') ?? $request->session()->get('danger'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
