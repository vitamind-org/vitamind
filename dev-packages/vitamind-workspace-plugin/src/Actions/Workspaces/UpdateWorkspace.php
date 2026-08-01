<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Plugins\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateWorkspace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Workspace $workspace, array $input): Workspace
    {
        if (isset($input['name'])) {
            $input['name'] = strtolower((string) $input['name']);
        }

        $this->validate($workspace, $input);

        $workspace->name = $input['name'];
        $workspace->save();

        return $workspace;
    }

    private function validate(Workspace $workspace, array $input): void
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workspaces', 'name')->ignore($workspace->id),
                'lowercase',
            ],
        ];

        Validator::make($input, $rules)->validate();
    }
}
