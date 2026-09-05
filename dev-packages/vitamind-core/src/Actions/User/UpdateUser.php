<?php

namespace VitaminD\Core\Actions\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use VitaminD\Core\Actions\Role\SyncRoles;
use VitaminD\Core\Events\UserChanged;
use VitaminD\Core\Events\UserChanging;
use VitaminD\Core\Models\User;
use VitaminD\PluginSdk\RegisterRole;

class UpdateUser
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): User
    {
        $this->validate($user, $input);

        UserChanging::dispatch($user, $input);

        $user->name = $input['name'];
        $user->email = $input['email'];
        $user->is_admin = (bool) ($input['is_admin'] ?? false);

        if (isset($input['password']) && filled($input['password'])) {
            $user->password = bcrypt($input['password']);
        }

        $user->save();

        /** @var ?User $actor */
        $actor = Auth::user();
        app(SyncRoles::class)->sync($user, $input['role'] ?? [], scopeContext: $actor);

        UserChanged::dispatch($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'is_admin' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'array'],
            'role.*' => Rule::in(array_keys(RegisterRole::get())),
            'password' => ['nullable', 'string', 'min:8'],
        ])->validate();
    }
}
