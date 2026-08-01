<?php

namespace VitaminD\Plugins\Workspace\Concerns;

use VitaminD\Plugins\Workspace\Models\Workspace;

/**
 * Adds workspace-scoping to the core PersonalAccessToken model. Applied by
 * the consuming application's own PersonalAccessToken model — core itself
 * must stay unaware of the workspace plugin.
 *
 * @property array<string> $abilities
 */
trait HasWorkspaceScopedTokens
{
    /**
     * Get the workspace IDs this token is scoped to.
     *
     * @return array<int>
     */
    public function getWorkspaceIds(): array
    {
        return collect($this->abilities)
            ->filter(fn (string $ability) => str_starts_with($ability, 'workspace:'))
            ->map(fn (string $ability) => (int) str_replace('workspace:', '', $ability))
            ->values()
            ->all();
    }

    /**
     * Check if the token has access to the given workspace.
     * Tokens with no workspace restrictions have access to all workspaces (backward compatible).
     */
    public function hasWorkspaceAccess(Workspace $workspace): bool
    {
        $workspaceIds = $this->getWorkspaceIds();

        if (empty($workspaceIds)) {
            return true;
        }

        return in_array($workspace->id, $workspaceIds);
    }
}
