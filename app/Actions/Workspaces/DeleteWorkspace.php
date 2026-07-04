<?php

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeleteWorkspace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function delete(User $user, Workspace $workspace, array $input): void
    {
        Validator::make($input, [
            'name' => 'required',
        ])->validate();

        if ($user->workspaces()->count() === 1) {
            throw ValidationException::withMessages([
                'name' => __('Cannot delete the last workspace.'),
            ]);
        }

        if ($user->current_workspace_id == $workspace->id) {
            throw ValidationException::withMessages([
                'name' => __('Cannot delete your current active workspace.'),
            ]);
        }

        $workspace->delete();

        $user->ensureHasDefaultWorkspace();
    }
}
