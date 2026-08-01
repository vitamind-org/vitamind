<?php

namespace VitaminD\Core\Actions\User;

use VitaminD\Core\Events\UserRemoved;
use VitaminD\Core\Events\UserRemoving;
use VitaminD\Core\Models\User;

class DeleteUser
{
    public function delete(User $user): void
    {
        UserRemoving::dispatch($user);

        $user->delete();

        UserRemoved::dispatch($user);
    }
}
