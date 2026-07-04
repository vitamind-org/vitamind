<?php

namespace App\Plugins\Local\Acme\MockProvider;

use App\Plugins\AbstractPlugin;
use App\Plugins\RegisterDNSProvider;
use App\DTOs\DynamicForm;
use App\DTOs\DynamicField;

class Plugin extends AbstractPlugin
{
    protected string $name = 'Mock DNS Provider';
    protected string $description = 'A mock DNS provider for testing core extraction.';

    public function boot(): void
    {
        RegisterDNSProvider::make('mock-dns')
            ->label('Mock DNS')
            ->handler(self::class)
            ->form(
                DynamicForm::make([
                    DynamicField::make('api_key')
                        ->passwordWithToggle()
                        ->label('API Secret Key')
                        ->description('Enter your mock API secret key'),
                    DynamicField::make('sandbox_mode')
                        ->checkbox()
                        ->label('Sandbox Mode')
                        ->default(true),
                ])
            )
            ->register();
    }
}
