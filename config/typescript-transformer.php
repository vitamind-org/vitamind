<?php

return [
    /*
     * The paths where typescript-transformer will look for classes to transform.
     */
    'searching_paths' => array_filter([
        base_path('vendor/vitamind/plugin-sdk/src/DTOs'),
        base_path('../packages/plugin-sdk/src/DTOs'),
        app_path('Plugins/Local'),
        storage_path('plugins'),
    ], 'is_dir'),

    /*
     * The path where the generated typescript file will be written.
     */
    'output_file' => resource_path('js/types/generated.d.ts'),

    /*
     * Collectors decide which classes will be transformed.
     */
    'collectors' => [
        Spatie\TypeScriptTransformer\Collectors\AnnotationCollector::class,
        Spatie\TypeScriptTransformer\Collectors\AttributeCollector::class,
    ],

    /*
     * Transformers transform classes into TypeScript types.
     */
    'transformers' => [
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
        Spatie\TypeScriptTransformer\Transformers\DtoTransformer::class,
        Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class,
    ],

    /*
     * The class name formatter.
     */
    'formatter' => Spatie\TypeScriptTransformer\Formatters\GracefulFormatter::class,
];
