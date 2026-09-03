<?php

namespace VitaminD\Plugins\Archive\Policies;

use VitaminD\Core\Models\User;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Policies\Concerns\ChecksVisibility;

class FilePolicy
{
    use ChecksVisibility;

    public function view(?User $user, File $file): bool
    {
        return $this->canView($user, $file);
    }

    // update/delete are restricted to the owner regardless of visibility
    // tier — a `public`/`app`/`workspace` item is readable, not writable,
    // by anyone other than its owner.
    public function update(User $user, File $file): bool
    {
        return $user->id === $file->owner_id;
    }

    public function delete(User $user, File $file): bool
    {
        return $user->id === $file->owner_id;
    }
}
