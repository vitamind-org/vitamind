<?php

namespace VitaminD\Plugins\Workspace\Mail;

use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public Workspace $workspace;

    public function __construct(Workspace $workspace)
    {
        $this->workspace = $workspace;
    }

    public function build(): static
    {
        return $this
            ->markdown('emails.workspace-invitation', [
                'acceptUrl' => route('workspaces.invitations.accept', ['workspace' => $this->workspace]),
            ])
            ->subject(__('Workspace Invitation'));
    }
}
