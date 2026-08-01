<?php

namespace VitaminD\Core\Actions\User;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Core\Events\UserStored;
use VitaminD\Core\Events\UserStoring;
use VitaminD\Core\Models\User;
use function VitaminD\Core\Support\authUserModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
            'role' => [
                'required',
                Rule::in([UserRole::ADMIN->value, UserRole::USER->value]),
            ],
        ])->validate();

        UserStoring::dispatch($input);

        $userModel = authUserModel();

        /** @var User $user */
        $user = $userModel::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => bcrypt($input['password']),
            'timezone' => 'UTC',
            'is_admin' => $input['role'] === UserRole::ADMIN->value,
        ]);

        UserStored::dispatch($user);

        return $user;
    }
}
