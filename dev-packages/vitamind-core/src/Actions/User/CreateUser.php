<?php

namespace VitaminD\Core\Actions\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use VitaminD\Core\Actions\Role\SyncRoles;
use VitaminD\Core\Events\UserStored;
use VitaminD\Core\Events\UserStoring;
use VitaminD\Core\Models\User;
use VitaminD\PluginSdk\RegisterRole;

use function VitaminD\Core\Support\authUserModel;

class CreateUser
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'is_admin' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'array'],
            'role.*' => Rule::in(array_keys(RegisterRole::get())),
        ])->validate();

        UserStoring::dispatch($input);

        $userModel = authUserModel();

        /** @var User $user */
        $user = $userModel::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => bcrypt($input['password']),
            'timezone' => 'UTC',
            'is_admin' => (bool) ($input['is_admin'] ?? false),
        ]);

        /** @var ?User $actor */
        $actor = Auth::user();
        app(SyncRoles::class)->sync($user, $input['role'] ?? [], scopeContext: $actor);

        UserStored::dispatch($user);

        return $user;
    }
}
