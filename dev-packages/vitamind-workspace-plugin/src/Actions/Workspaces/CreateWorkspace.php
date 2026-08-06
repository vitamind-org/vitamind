<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\Core\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function create(User $user, array $input): Workspace
    {
        $this->validate($input);

        return DB::transaction(function () use ($user, $input) {
            $workspace = new Workspace([
                'name' => $input['name'],
            ]);
            $workspace->slug = Str::slug($input['name']);
            $workspace->save();

            // A self-created workspace always supersedes the user's previous
            // default membership.
            UserWorkspace::query()
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $workspace->users()->create([
                'user_id' => $user->id,
                'role' => UserRole::OWNER,
                'is_default' => true,
            ]);

            $user->current_workspace_id = $workspace->id;
            $user->save();

            return $workspace;
        });
    }

    private function validate(array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9- ]+$/',
                $this->uniqueSlugRule(),
            ],
        ], [
            'name.regex' => __('Workspace name may only contain letters, numbers, dashes, and spaces.'),
        ])->validate();
    }

    /**
     * Names are free-text, but uniqueness is enforced on the normalized
     * slug so names differing only by case, whitespace, or punctuation
     * can't coexist. Kept on the `name` attribute (rather than a separate
     * `slug` rule) so any failure surfaces under the field the user
     * actually sees and edited.
     */
    private function uniqueSlugRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $slug = Str::slug((string) $value);

            if ($slug === '') {
                $fail(__('Workspace name must contain at least one letter or number.'));

                return;
            }

            if (Workspace::query()->where('slug', $slug)->exists()) {
                $fail(__('This workspace name is already in use.'));
            }
        };
    }
}
