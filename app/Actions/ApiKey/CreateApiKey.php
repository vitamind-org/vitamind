<?php

namespace App\Actions\ApiKey;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\NewAccessToken;

class CreateApiKey
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input): NewAccessToken
    {
        $this->validate($user, $input);

        $abilities = ['read'];
        if ($input['permission'] === 'write') {
            $abilities[] = 'write';
        }

        // If workspaces feature is enabled and workspaces are selected
        if (config('vitamin-d.features.workspaces', false) && ! empty($input['workspaces'])) {
            foreach ($input['workspaces'] as $workspaceId) {
                $abilities[] = 'workspace:'.$workspaceId;
            }
        }

        return $user->createToken($input['name'], $abilities);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(User $user, array $input): void
    {
        $workspaceIds = config('vitamin-d.features.workspaces', false) 
            ? $user->workspaces()->pluck('workspaces.id')->toArray() 
            : [];

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'permission' => ['required', Rule::in(['read', 'write'])],
            'workspaces' => ['nullable', 'array'],
            'workspaces.*' => ['required', 'integer', Rule::in($workspaceIds)],
        ])->validate();
    }
}
