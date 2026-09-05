<?php

namespace VitaminD\Plugins\TodoPlugin\Models;

use Illuminate\Database\Eloquent\Model;
use VitaminD\Plugins\TodoPlugin\Plugin;
use VitaminD\PluginSdk\Concerns\BelongsToWorkspace;

class Todo extends Model
{
    use BelongsToWorkspace;

    protected $table = 'todos';

    // `workspace_id` is intentionally not fillable: BelongsToWorkspace stamps
    // it, and leaving it out keeps a request body from placing a todo into
    // another workspace.
    protected $fillable = ['title', 'is_done'];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    /**
     * Demonstrates checking a registered role with `hasRole()`: only a Todo
     * Manager may delete a todo — anyone can still create one or toggle it
     * done. Enforced here, at the model layer, because the generic
     * plugin-page CRUD controller this demo relies on
     * (`VitaminD\Core\Http\Controllers\Admin\PluginPageController`) has no
     * per-action authorization hook of its own. A plugin with its own
     * routes/controller should prefer a real Policy + `$this->authorize()`
     * instead — this is a minimal stand-in so the example stays reachable
     * without that extra scaffolding.
     */
    protected static function booted(): void
    {
        static::deleting(function (Todo $todo): void {
            if (! auth()->user()?->hasRole(Plugin::managerRoleKey())) {
                abort(403, 'Only a Todo Manager can delete todos.');
            }
        });
    }
}
