<?php

namespace VitaminD\Plugins\Workspace\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Adds workspace relations to the core User model. Applied by the consuming
 * application's own User model — the core package itself must stay unaware
 * of the workspace plugin.
 *
 * @property ?int $current_workspace_id
 */
trait HasWorkspaces
{
    public function allWorkspaces(): Builder
    {
        return Workspace::query()
            ->whereHas('users', fn (Builder $q) => $q->where('user_id', $this->id));
    }

    public function workspaces(): HasManyThrough
    {
        return $this->hasManyThrough(Workspace::class, UserWorkspace::class, 'user_id', 'id', 'id', 'workspace_id');
    }

    /**
     * @return HasOne<Workspace, covariant $this>
     */
    public function currentWorkspace(): HasOne
    {
        return $this->HasOne(Workspace::class, 'id', 'current_workspace_id');
    }

    /**
     * Self-heals a stale `current_workspace_id` by repointing it at the
     * user's default (anchor) membership. Never creates a workspace — a
     * user's first-ever membership is only ever obtained by explicitly
     * accepting an invitation or creating a workspace via the onboarding
     * screen (see `EnsureWorkspaceOnboarded`).
     */
    public function ensureHasDefaultWorkspace(): ?Workspace
    {
        /** @var ?UserWorkspace $default */
        $default = UserWorkspace::query()
            ->where('user_id', $this->id)
            ->where('is_default', true)
            ->first();

        $workspace = $default?->workspace;

        if ($workspace) {
            $this->current_workspace_id = $workspace->id;
            $this->save();
        }

        return $workspace;
    }
}
