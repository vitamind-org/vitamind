<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Core\Models\User;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;

class AcceptWorkspaceInvite
{
    public function accept(UserWorkspace $invite, User $user): void
    {
        $isFirstMembership = $user->workspaces()->count() === 0;

        $invite->user_id = $user->id;
        $invite->email = null;
        if ($isFirstMembership) {
            $invite->is_default = true;
        }
        $invite->save();

        $user->current_workspace_id = $invite->workspace_id;
        $user->save();
    }
}
