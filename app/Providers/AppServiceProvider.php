<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();

        Vite::prefetch(concurrency: 3);

        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(
            \App\Models\PersonalAccessToken::class
        );
    }
}
