<?php

namespace VitaminD\Core\Events;

use VitaminD\Core\Models\PersonalAccessToken;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiKeyRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PersonalAccessToken $apiKey,
    ) {}
}
