<?php

namespace VitaminD\Plugins\Archive\Policies;

use VitaminD\Core\Models\User;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Archive\Policies\Concerns\ChecksVisibility;

class FolderPolicy
{
    use ChecksVisibility;

    public function view(?User $user, Folder $folder): bool
    {
        return $this->canView($user, $folder);
    }

    // update/delete are restricted to the owner regardless of visibility
    // tier — a `public`/`app`/`workspace` item is readable, not writable,
    // by anyone other than its owner.
    public function update(User $user, Folder $folder): bool
    {
        return $user->id === $folder->owner_id;
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->id === $folder->owner_id;
    }
}
