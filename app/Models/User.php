<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\HasTimezoneTimestamps;
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
 * @property ?int $current_project_id
 * @property bool $is_admin
 * @property ?Project $currentProject
 * @property Collection<int, Project> $projects
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
        'current_project_id',
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

    public function allProjects(): Builder
    {
        return Project::query()
            ->whereHas('users', fn (Builder $q) => $q->where('user_id', $this->id));
    }

    public function projects(): HasManyThrough
    {
        return $this->hasManyThrough(Project::class, UserProject::class, 'user_id', 'id', 'id', 'project_id');
    }

    /**
     * @return HasOne<Project, covariant $this>
     */
    public function currentProject(): HasOne
    {
        return $this->HasOne(Project::class, 'id', 'current_project_id');
    }

    public function ensureHasDefaultProject(): Project
    {
        /** @var ?Project $project */
        $project = $this->projects()->first();

        if (! $project) {
            $project = new Project;
            $project->name = 'default';
            $project->save();

            $project->users()->create([
                'user_id' => $this->id,
                'role' => UserRole::OWNER,
            ]);
        }

        $this->current_project_id = $project->id;
        $this->save();

        return $project;
    }

    public function hasRolesInProject(Project $project, array $roles): bool
    {
        return $project->users()
            ->where('user_id', $this->id)
            ->whereIn('role', $roles)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }
}
