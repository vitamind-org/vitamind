<?php

namespace App\Models;

use App\Traits\HasTimezoneTimestamps;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<string> $abilities
 * @property Carbon $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasTimezoneTimestamps;

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
