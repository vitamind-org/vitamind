<?php

namespace VitaminD\PluginSdk\Tests\Fixtures;

use VitaminD\PluginSdk\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for a plugin's own model, so the trait can be tested without
 * pulling in a real plugin. Note `workspace_id` is intentionally absent from
 * $fillable — the trait stamps it directly, and it must not be mass-assignable.
 */
class WorkspaceScopedRecord extends Model
{
    use BelongsToWorkspace;

    protected $table = 'sdk_workspace_scoped_records';

    protected $fillable = ['title'];
}
