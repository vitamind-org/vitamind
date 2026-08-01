<?php

namespace VitaminD\Plugins\Workspace\Actions\Workspaces;

use VitaminD\Core\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class GetWorkspaces
{
    public function get(User $user, array $input, int $perPage = 10): Collection
    {
        $validated = $this->validate($input);

        $workspacesQuery = $user->allWorkspaces();

        if (! empty($validated['query'])) {
            $workspacesQuery->where('name', 'like', "%{$validated['query']}%");
        }

        $page = $validated['page'] ?? 1;

        return $workspacesQuery
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
    }

    private function validate(array $input): array
    {
        return Validator::make($input, [
            'query' => [
                'nullable',
                'string',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ])->validate();
    }
}
