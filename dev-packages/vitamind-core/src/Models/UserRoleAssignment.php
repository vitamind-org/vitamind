<?php

namespace VitaminD\Core\Models;

use Carbon\Carbon;

/**
 * A single row in `user_roles` — a user holding one role, optionally scoped
 * (`scope_type`/`scope_id`). Named distinctly from `VitaminD\Core\Enums\UserRole`
 * (the retiring workspace-tier enum) to avoid confusion between the two: this
 * class is a database-backed assignment record, not an enum of fixed values —
 * `role` here is any key registered via `VitaminD\PluginSdk\RegisterRole`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $role
 * @property ?string $scope_type
 * @property ?int $scope_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserRoleAssignment extends AbstractModel
{
    protected $table = 'user_roles';

    protected $fillable = [
        'user_id',
        'role',
        'scope_type',
        'scope_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'scope_id' => 'integer',
        ];
    }
}
