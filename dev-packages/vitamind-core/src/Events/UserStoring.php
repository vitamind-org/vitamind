<?php

namespace VitaminD\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

class UserStoring
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly array $input,
    ) {}
}
