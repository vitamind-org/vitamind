<?php

namespace VitaminD\Plugins\Workspace\Models;

use VitaminD\Plugins\Workspace\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property ?int $user_id
 * @property ?string $email
 * @property UserRole $role
 * @property ?User $user
 * @property Workspace $workspace
 */
class UserWorkspace extends AbstractModel
{
    protected $table = 'user_workspace';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'email',
        'role',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'user_id' => 'integer',
        'role' => UserRole::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
