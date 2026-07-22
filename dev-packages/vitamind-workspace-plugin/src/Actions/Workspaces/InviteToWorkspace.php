<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Plugins\Workspace\Enums\UserRole;
use VitaminD\Plugins\Workspace\Mail\WorkspaceInvitation;
use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class InviteToWorkspace
{
    public function invite(Workspace $workspace, array $input): void
    {
        $this->validate($workspace, $input);

        $workspace->users()->create([
            'email' => $input['email'],
            'role' => UserRole::from($input['role']),
        ]);

        try {
            Mail::to($input['email'])->send(new WorkspaceInvitation($workspace));
        } catch (Throwable) {
            //
        }
    }

    protected function validate(Workspace $workspace, array $input): void
    {
        Validator::make($input, $this->rules($workspace), [
            'email.unique' => __('This user has already been invited to the workspace.'),
        ])->validate();
    }

    protected function rules(Workspace $workspace): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::unique('user_workspace')->where(function (Builder $query) use ($workspace) {
                    $query->where('workspace_id', $workspace->id);
                }),
                Rule::notIn([
                    ...$workspace->registeredUsers()->pluck('users.email'),
                ]),
            ],
            'role' => [
                'required',
                Rule::in([
                    UserRole::ADMIN,
                    UserRole::USER,
                ]),
            ],
        ];
    }
}
