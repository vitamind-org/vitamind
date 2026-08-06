<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Plugins\Workspace\Mail\WorkspaceInvitation;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ResendWorkspaceInvitation
{
    public function resend(UserWorkspace $invite): void
    {
        $acceptUrl = URL::temporarySignedRoute(
            'workspaces.invitations.accept',
            now()->addDays(7),
            ['workspace' => $invite->workspace_id, 'invite' => $invite->id],
        );

        try {
            Mail::to($invite->email)->send(new WorkspaceInvitation($invite->workspace, $acceptUrl));
        } catch (Throwable) {
            //
        }
    }
}
