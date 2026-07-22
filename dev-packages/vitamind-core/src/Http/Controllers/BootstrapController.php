<?php

namespace VitaminD\Core\Http\Controllers;

use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Get;

final class BootstrapController
{
    #[Get('/bootstrap', name: 'bootstrap.show')]
    public function __invoke(GetBootstrap $getBootstrap): JsonResponse
    {
        return response()->json($getBootstrap->handle());
    }
}
