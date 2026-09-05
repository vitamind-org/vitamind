<?php

namespace VitaminD\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Traits\HasTimezoneTimestamps;

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

    /**
     * Casts defensively: `Model::create()` never re-fetches the row it just
     * inserted, so an in-memory instance built without explicitly setting
     * `is_admin` never sees the column's DB-level `default(false)` — the
     * attribute is simply absent, and the `boolean` cast on a missing
     * attribute resolves to `null` rather than `false`. A bare `$this->is_admin`
     * would then violate this method's `bool` return type.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Omitted scope args fall back to the bound `RoleScopeResolver`
     * (global/unscoped by default, or whatever a tenancy-like plugin —
     * e.g. `vitamind-workspace-plugin` — has bound instead). Passing both
     * explicitly skips resolution entirely and checks that exact scope.
     */
    public function hasRole(string $role, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        if ($scopeType === null && $scopeId === null) {
            $resolved = app(RoleScopeResolver::class)->resolve($this);
            $scopeType = $resolved['scope_type'] ?? null;
            $scopeId = $resolved['scope_id'] ?? null;
        }

        return UserRoleAssignment::query()
            ->where('user_id', $this->id)
            ->where('role', $role)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->exists();
    }
}
