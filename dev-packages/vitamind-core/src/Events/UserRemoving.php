<?php

namespace VitaminD\Core\Events;

use VitaminD\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRemoving
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
