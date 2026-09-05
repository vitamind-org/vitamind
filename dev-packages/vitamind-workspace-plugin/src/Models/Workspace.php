<?php

namespace VitaminD\Plugins\Workspace\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use VitaminD\Core\Models\AbstractModel;
use VitaminD\Core\Traits\HasTimezoneTimestamps;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
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

    /**
     * Safety net so `slug` (NOT NULL, unique) is never left unset by a
     * caller that only assigns `name` directly — `CreateWorkspace` and
     * `UpdateWorkspace` set it explicitly (and validate its uniqueness)
     * themselves, so this only kicks in for anything that bypasses them.
     */
    protected static function booted(): void
    {
        static::saving(function (Workspace $workspace): void {
            if (! $workspace->slug || $workspace->isDirty('name')) {
                $workspace->slug = Str::slug($workspace->name)
                    ?: 'workspace-'.Str::lower(Str::random(8));
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(UserWorkspace::class, 'workspace_id');
    }

    public function registeredUsers(): HasManyThrough
    {
        return $this->hasManyThrough(config('auth.providers.users.model'), UserWorkspace::class, 'workspace_id', 'id', 'id', 'user_id');
    }
}
