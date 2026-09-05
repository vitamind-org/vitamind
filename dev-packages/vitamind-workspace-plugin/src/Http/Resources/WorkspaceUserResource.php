<?php

namespace VitaminD\Plugins\Workspace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
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
            'role' => $this->displayRole(),
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

    /**
     * A pending invitation shows its stored choice directly (short role key,
     * or 'admin' for an Admin grant). An accepted membership shows 'owner'
     * for the workspace's owner (`workspaces.owner_id` — plain data, not a
     * role); otherwise, any workspace-scoped role the member happens to
     * hold (workspace-plugin registers none of its own, so this is always
     * an app-registered role, or none — 'member' as a plain fallback
     * label). `is_admin` is a separate, unscoped mechanism and never shown
     * here (see `HasRolePolicies`).
     */
    private function displayRole(): ?string
    {
        if ($this->user_id === null) {
            return $this->is_admin_grant ? 'admin' : $this->shortKey($this->invited_role);
        }

        if ($this->workspace && $this->workspace->owner_id === $this->user_id) {
            return 'owner';
        }

        $roleKey = DB::table('user_roles')
            ->where('user_id', $this->user_id)
            ->where('scope_type', 'workspace')
            ->where('scope_id', $this->workspace_id)
            ->value('role');

        return $roleKey ? $this->shortKey($roleKey) : 'member';
    }

    private function shortKey(?string $key): ?string
    {
        return $key !== null ? Str::afterLast($key, '.') : null;
    }
}
