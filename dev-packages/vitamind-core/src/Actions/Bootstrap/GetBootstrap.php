<?php

namespace VitaminD\Core\Actions\Bootstrap;

use Illuminate\Support\Facades\Cache;

final class GetBootstrap
{
    public const string VERSION_CACHE_KEY = 'bootstrap.version';

    /** @var array<string, mixed>|null */
    private ?array $cachedConfigs = null;

    /**
     * @return array{
     *     version: string,
     *     configs: array<string, mixed>,
     * }
     */
    public function handle(): array
    {
        return [
            'version' => $this->version(),
            'configs' => $this->configs(),
        ];
    }

    public function version(): string
    {
        if (! app()->isProduction()) {
            return $this->computeVersion();
        }

        return Cache::rememberForever(
            self::VERSION_CACHE_KEY,
            fn (): string => $this->computeVersion(),
        );
    }

    public function computeVersion(): string
    {
        return substr(md5(serialize($this->configs())), 0, 16);
    }

    public static function forgetVersion(): void
    {
        Cache::forget(self::VERSION_CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function configs(): array
    {
        return $this->cachedConfigs ??= [
            'server_provider' => [
                'providers' => config('server-provider.providers') ?? [],
            ],
            'dns_provider' => [
                'providers' => config('dns-provider.providers') ?? [],
            ],
            'plugins' => [
                'views' => config('plugins.views') ?? [],
            ],
        ];
    }
}
