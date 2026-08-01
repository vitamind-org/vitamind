<?php

namespace VitaminD\Core\Events;

use VitaminD\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiKeyStoring
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly User $user,
        public readonly array $input,
    ) {}
}
