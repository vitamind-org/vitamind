<?php

namespace App\Support\WhatsApp;

use App\Models\WaInstance;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for a single workspace's GoWA instance, built from that
 * workspace's stored connection record — never a shared/global client,
 * since each instance has its own base URL and Basic Auth credential
 * (design.md D1/D5).
 */
class GowaHttp
{
    public static function client(WaInstance $instance): PendingRequest
    {
        return Http::baseUrl(rtrim($instance->base_url, '/'))
            ->withBasicAuth($instance->basic_auth_username, $instance->basic_auth_password)
            ->acceptJson()
            ->timeout(30);
    }
}
