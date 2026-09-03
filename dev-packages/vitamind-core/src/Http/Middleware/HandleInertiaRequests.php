<?php

namespace VitaminD\Core\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
use VitaminD\Core\Actions\Plugins\ResolvePluginPages;
use VitaminD\Core\Http\Resources\UserResource;
use VitaminD\Core\Models\User;
use VitaminD\Core\Support\InertiaSharedData;

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

        $shared = [
            ...parent::share($request),
            'auth' => $user ? [
                'user' => UserResource::make($user),
            ] : null,
            'features' => $features,
            'pluginPages' => app(ResolvePluginPages::class)->handle(),
            'csrf_token' => csrf_token(),
            'bootstrap_version' => app(GetBootstrap::class)->version(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error') ?? $request->session()->get('danger'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'data' => fn () => $request->session()->get('data'),
            ],
        ];

        return array_replace_recursive($shared, InertiaSharedData::resolve($request));
    }
}
