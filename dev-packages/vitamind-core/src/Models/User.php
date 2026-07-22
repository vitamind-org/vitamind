<?php

namespace VitaminD\Core\Models;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Core\Traits\HasTimezoneTimestamps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $two_factor_recovery_codes
 * @property string $two_factor_secret
 * @property string $timezone
 * @property ?int $current_workspace_id
 * @property bool $is_admin
 * @property ?Workspace $currentWorkspace
 * @property Collection<int, Workspace> $workspaces
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasTimezoneTimestamps;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'current_workspace_id',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function allWorkspaces(): Builder
    {
        return Workspace::query()
            ->whereHas('users', fn (Builder $q) => $q->where('user_id', $this->id));
    }

    public function workspaces(): HasManyThrough
    {
        return $this->hasManyThrough(Workspace::class, UserWorkspace::class, 'user_id', 'id', 'id', 'workspace_id');
    }

    /**
     * @return HasOne<Workspace, covariant $this>
     */
    public function currentWorkspace(): HasOne
    {
        return $this->HasOne(Workspace::class, 'id', 'current_workspace_id');
    }

    public function ensureHasDefaultWorkspace(): Workspace
    {
        /** @var ?Workspace $workspace */
        $workspace = $this->workspaces()->first();

        if (! $workspace) {
            $workspace = new Workspace;
            $workspace->name = 'default';
            $workspace->save();

            $workspace->users()->create([
                'user_id' => $this->id,
                'role' => UserRole::OWNER,
            ]);
        }

        $this->current_workspace_id = $workspace->id;
        $this->save();

        return $workspace;
    }

    public function hasRolesInWorkspace(Workspace $workspace, array $roles): bool
    {
        return $workspace->users()
            ->where('user_id', $this->id)
            ->whereIn('role', $roles)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }
}
