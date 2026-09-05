<?php

namespace VitaminD\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use VitaminD\Core\Contracts\RoleScopeResolver;
use VitaminD\Core\Models\User;
use VitaminD\Core\Models\UserRoleAssignment;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'roles' => $this->roleKeysInCurrentScope($request),
            'two_factor_enabled' => (bool) $this->two_factor_secret,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The role keys this user holds in the scope the *viewing* request is
     * currently in (e.g. the admin's active workspace, if any) — the same
     * scope `CreateUser`/`UpdateUser` assign into, so the edit form's
     * pre-filled selection matches what a resubmission would actually
     * change.
     *
     * @return array<int, string>
     */
    private function roleKeysInCurrentScope(Request $request): array
    {
        $scope = app(RoleScopeResolver::class)->resolve($request->user() ?? $this->resource);

        return UserRoleAssignment::query()
            ->where('user_id', $this->id)
            ->where('scope_type', $scope['scope_type'] ?? null)
            ->where('scope_id', $scope['scope_id'] ?? null)
            ->pluck('role')
            ->all();
    }
}
