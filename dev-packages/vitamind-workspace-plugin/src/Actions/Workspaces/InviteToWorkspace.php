<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
use VitaminD\Plugins\Workspace\Mail\WorkspaceInvitation;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\PluginSdk\RegisterRole;

class InviteToWorkspace
{
    /**
     * Sentinel value for the "grant system Admin" option — never collides
     * with a real `RegisterRole` key, since every registered key is
     * prefixed with the registering package's identifier (always contains
     * a `.`).
     */
    public const ADMIN_OPTION = 'admin';

    /**
     * Sentinel value for "no role, no Admin — just a plain member". Role
     * selection is optional (see `rules()`), but Radix `Select` cannot
     * represent an empty-string item value, so the invite form sends this
     * explicit sentinel instead of omitting the field.
     */
    public const NONE_OPTION = 'none';

    public function invite(Workspace $workspace, array $input): void
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower($input['email']);
        }

        $this->validate($workspace, $input);

        $email = $input['email'];
        $role = $input['role'] ?? self::NONE_OPTION;
        $isAdminGrant = $role === self::ADMIN_OPTION;
        $invitedRole = ($isAdminGrant || $role === self::NONE_OPTION) ? null : $role;

        $userWorkspace = $workspace->users()->create([
            'email' => $email,
            'is_admin_grant' => $isAdminGrant,
            'invited_role' => $invitedRole,
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
                'nullable',
                Rule::in([
                    self::ADMIN_OPTION,
                    self::NONE_OPTION,
                    ...$this->invitableRoleKeys(),
                ]),
            ],
        ];
    }

    /**
     * Every currently registered role — driven entirely by the
     * `RegisterRole` registry, so an app-registered domain-specific role
     * becomes invitable with no plugin-specific code change. Workspace-plugin
     * registers no roles of its own (ownership is `workspaces.owner_id`, not
     * a role), so no exclusion is needed here.
     */
    private function invitableRoleKeys(): array
    {
        return collect(RegisterRole::get())
            ->keys()
            ->values()
            ->all();
    }
}
