<?php

namespace VitaminD\Plugins\Workspace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use VitaminD\Core\Http\Resources\UserResource;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;

/**
 * @mixin UserWorkspace
 */
class WorkspaceUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPending = $this->user_id === null && $this->email !== null;

        // WorkspaceResource serializes this for every workspace member, not
        // just the current viewer — without this check, every member would
        // receive a valid signed accept link for invitations addressed to
        // other people.
        $isOwnInvite = $isPending
            && $request->user()
            && Str::lower((string) $this->email) === Str::lower($request->user()->email);

        return [
            'id' => $this->id,
            // `email` is cleared on the row once accepted (see
            // AcceptWorkspaceInvite) so per-workspace invite uniqueness stays
            // meaningful — fall back to the joined user's email so accepted
            // members still show an email in the workspace user list.
            'email' => $this->email ?? $this->user?->email,
            'workspace_id' => $this->workspace_id,
            'workspace_name' => $this->workspace->name ?? null,
            'user' => UserResource::make($this->user),
            'role' => $this->role->value,
            'type' => $this->user_id !== null ? 'user' : 'invitation',
            // Signed, time-limited accept link for pending invitations — the
            // accept route requires a valid signature, so the frontend can no
            // longer construct this URL itself.
            'accept_url' => $isOwnInvite
                ? URL::temporarySignedRoute(
                    'workspaces.invitations.accept',
                    now()->addDays(7),
                    ['workspace' => $this->workspace_id, 'invite' => $this->id],
                )
                : null,
        ];
    }
}
