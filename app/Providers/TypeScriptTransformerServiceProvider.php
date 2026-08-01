<?php

namespace App\Providers;

use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;

/**
 * This is the TypeScript transformer's configuration surface for this
 * project — there is no config/typescript-transformer.php file. The base
 * spatie/laravel-typescript-transformer package supports both a plain config
 * file and this ServiceProvider-based factory; this project uses the latter
 * so the directory list can be computed (e.g. `is_dir()`-filtered) rather
 * than hardcoded.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $directories = array_filter([
            app_path(),
            base_path('vendor/vitamind/core/src/DTOs'),
            base_path('vendor/vitamind/workspace-plugin/src/DTOs'),
            base_path('vendor/vitamind/plugin-sdk/src/DTOs'),
            storage_path('plugins'),
        ], 'is_dir');

        $config
            ->extension(new \Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension())
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(...$directories)
            ->outputDirectory(resource_path('js/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(PrettierFormatter::class);
    }
}
