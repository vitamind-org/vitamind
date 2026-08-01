<?php

namespace VitaminD\Plugins\TodoPlugin\Models;

use VitaminD\PluginSdk\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

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
}
