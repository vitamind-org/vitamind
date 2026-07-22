<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Plugins\Workspace\Enums\UserRole;
use VitaminD\Plugins\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateWorkspace
{
    public function create(User $user, array $input): Workspace
    {
        $this->validate($input);

        $workspace = new Workspace([
            'name' => $input['name'],
        ]);
        $workspace->save();

        $workspace->users()->create([
            'user_id' => $user->id,
            'role' => UserRole::OWNER,
        ]);

        $user->current_workspace_id = $workspace->id;
        $user->save();

        return $workspace;
    }

    private function validate(array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:workspaces,name',
                'lowercase',
            ],
        ])->validate();
    }
}
