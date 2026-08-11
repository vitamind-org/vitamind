<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @php
            $pageComponentPath = "resources/js/pages/{$page['component']}.tsx";

            // Distributed plugin pages (@plugin/{name}/{page}) live under
            // vendor/vitamind/{name}/resources/js/pages/, not
            // resources/js/pages/. vendor/vitamind/* may be a symlink (this
            // monorepo's dev-packages/ path-repository setup) — Vite's
            // manifest records the *resolved* real path, so realpath() here
            // mirrors the fix already applied for routing in
            // CoreServiceProvider::registerRoutes().
            if (str_starts_with($page['component'], '@plugin/')) {
                [$pluginName, $pagePath] = explode('/', substr($page['component'], strlen('@plugin/')), 2);
                $realPath = realpath(base_path("vendor/vitamind/{$pluginName}/resources/js/pages/{$pagePath}.tsx"));
                $pageComponentPath = $realPath
                    ? ltrim(substr($realPath, strlen(realpath(base_path()))), DIRECTORY_SEPARATOR)
                    : null;
            }
        @endphp
        @vite(array_values(array_filter(['resources/js/app.tsx', $pageComponentPath])))
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
