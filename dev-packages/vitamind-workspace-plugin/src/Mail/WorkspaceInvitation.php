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

    public string $acceptUrl;

    public function __construct(Workspace $workspace, string $acceptUrl)
    {
        $this->workspace = $workspace;
        $this->acceptUrl = $acceptUrl;
    }

    public function build(): static
    {
        return $this
            ->markdown('emails.workspace-invitation', [
                'acceptUrl' => $this->acceptUrl,
            ])
            ->subject(__('Workspace Invitation'));
    }
}
