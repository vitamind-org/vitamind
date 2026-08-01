<?php

namespace VitaminD\Plugins\Workspace\Models;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Core\Models\User;
use VitaminD\Core\Models\AbstractModel;
use VitaminD\Core\Traits\HasTimezoneTimestamps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, UserWorkspace> $users
 * @property Collection<int, User> $registeredUsers
 */
class Workspace extends AbstractModel
{
    use HasTimezoneTimestamps;

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(UserWorkspace::class, 'workspace_id');
    }

    public function registeredUsers(): HasManyThrough
    {
        return $this->hasManyThrough(config('auth.providers.users.model'), UserWorkspace::class, 'workspace_id', 'id', 'id', 'user_id');
    }

    public function hasRoles(User $user, array $roles): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();
    }

    public function role(User $user): UserRole
    {
        /** @var UserWorkspace $userWorkspace */
        $userWorkspace = $this->users()->where('user_id', $user->id)->firstOrFail();

        return $userWorkspace->role;
    }
}
