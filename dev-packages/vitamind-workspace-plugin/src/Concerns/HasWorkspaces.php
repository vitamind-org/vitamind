<?php

namespace VitaminD\Plugins\Workspace\Concerns;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function ensureHasDefaultWorkspace(): Workspace
    {
        /** @var ?Workspace $workspace */
        $workspace = $this->workspaces()->first();

        if (! $workspace) {
            $workspace = new Workspace;
            $workspace->name = 'default';
            $workspace->save();

            $workspace->users()->create([
                'user_id' => $this->id,
                'role' => UserRole::OWNER,
            ]);
        }

        $this->current_workspace_id = $workspace->id;
        $this->save();

        return $workspace;
    }

    public function hasRolesInWorkspace(Workspace $workspace, array $roles): bool
    {
        return $workspace->users()
            ->where('user_id', $this->id)
            ->whereIn('role', $roles)
            ->exists();
    }
}
