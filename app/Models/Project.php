<?php

namespace App\Models;

class Project extends AbstractModel
{
    protected $fillable = [
        'name',
    ];

    public function users()
    {
        return $this->hasMany(UserProject::class, 'project_id');
    }

    public function hasRoles(User $user, array $roles): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();
    }
}
