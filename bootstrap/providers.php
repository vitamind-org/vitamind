<?php

$providers = [
    VitaminD\Core\Providers\CoreServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\TypeScriptTransformerServiceProvider::class,
];

if (config('vitamin-d.features.workspaces')) {
    $providers[] = VitaminD\Plugins\Workspace\Providers\WorkspaceServiceProvider::class;
}

return $providers;
