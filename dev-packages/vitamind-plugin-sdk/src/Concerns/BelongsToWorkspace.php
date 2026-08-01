<?php

namespace VitaminD\PluginSdk\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * Scopes a plugin's model to the acting user's current workspace: reads are
 * filtered to that workspace, and new records are stamped with it on create.
 *
 * Deliberately convention-based rather than typed against the workspace
 * plugin: it touches only the `vitamin-d.features.workspaces` flag, the
 * `current_workspace_id` attribute on the authenticated user, and the model's
 * own `workspace_id` column. That keeps the SDK free of any dependency on
 * `vitamind/workspace-plugin` — the same way Laravel's own SoftDeletes knows
 * the name `deleted_at` without knowing anything about your domain.
 *
 * Because of that, applying this trait is *relaxed*: with the workspaces
 * feature disabled (a single-tenant install, where the workspace plugin isn't
 * even present), every hook below turns into a no-op and the model behaves
 * exactly as an unscoped global model. A plugin can therefore use this and
 * still install cleanly on single-tenant projects — it adapts rather than
 * demanding multi-tenancy.
 *
 * The model's migration must add a nullable `workspace_id` column. Leave it
 * unconstrained (no `->constrained()`): on a single-tenant install there is no
 * `workspaces` table for the foreign key to point at.
 *
 * Escape hatch for deliberate cross-workspace reads (reports, admin tooling):
 * `Model::withoutGlobalScope('workspace')`.
 *
 * @property ?int $workspace_id
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $workspaceId = static::currentWorkspaceId();

            if ($workspaceId === null) {
                return;
            }

            // Table-qualified: an unqualified `workspace_id` is ambiguous the
            // moment the query joins another workspace-scoped table.
            $query->where($query->getModel()->getTable().'.workspace_id', $workspaceId);
        });

        static::creating(function (Model $model): void {
            if ($model->workspace_id !== null) {
                return;
            }

            $workspaceId = static::currentWorkspaceId();

            if ($workspaceId !== null) {
                // Assigned directly rather than through fill(): `workspace_id`
                // must stay out of $fillable so a crafted request body can't
                // mass-assign a record into someone else's workspace.
                $model->workspace_id = $workspaceId;
            }
        });
    }

    /**
     * Null means "do not scope at all", which covers three distinct cases:
     * the workspaces feature is off, nobody is authenticated (console
     * commands, queued jobs), or the user has no current workspace.
     *
     * Returning null — rather than scoping to a null workspace — is the
     * deliberate choice: `where('workspace_id', null)` compares against SQL
     * NULL and silently matches nothing, so an artisan command would quietly
     * see an empty table instead of the data it was written to operate on.
     */
    protected static function currentWorkspaceId(): ?int
    {
        if (! Config::get('vitamin-d.features.workspaces', false)) {
            return null;
        }

        $workspaceId = Auth::user()?->current_workspace_id;

        return $workspaceId === null ? null : (int) $workspaceId;
    }
}
