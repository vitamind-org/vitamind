<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Plugins\Workspace\Mail\WorkspaceInvitation;
use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class InviteToWorkspace
{
    public function invite(Workspace $workspace, array $input): void
    {
        $this->validate($workspace, $input);

        $email = Str::lower($input['email']);

        $userWorkspace = $workspace->users()->create([
            'email' => $email,
            'role' => UserRole::from($input['role']),
        ]);

        $acceptUrl = URL::temporarySignedRoute(
            'workspaces.invitations.accept',
            now()->addDays(7),
            ['workspace' => $workspace->id, 'invite' => $userWorkspace->id],
        );

        try {
            Mail::to($email)->send(new WorkspaceInvitation($workspace, $acceptUrl));
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
