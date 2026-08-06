<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Plugins\Workspace\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UpdateWorkspace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Workspace $workspace, array $input): Workspace
    {
        $this->validate($workspace, $input);

        $workspace->name = $input['name'];
        $workspace->slug = Str::slug($input['name']);
        $workspace->save();

        return $workspace;
    }

    private function validate(Workspace $workspace, array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9- ]+$/',
                $this->uniqueSlugRule($workspace),
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
    private function uniqueSlugRule(Workspace $workspace): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
            $slug = Str::slug((string) $value);

            if ($slug === '') {
                $fail(__('Workspace name must contain at least one letter or number.'));

                return;
            }

            $collides = Workspace::query()
                ->where('slug', $slug)
                ->where('id', '!=', $workspace->id)
                ->exists();

            if ($collides) {
                $fail(__('This workspace name is already in use.'));
            }
        };
    }
}
