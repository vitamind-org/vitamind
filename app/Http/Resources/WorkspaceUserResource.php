<?php

namespace App\Http\Resources;

use App\Models\UserWorkspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserWorkspace
 */
class WorkspaceUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'workspace_id' => $this->workspace_id,
            'workspace_name' => $this->workspace->name ?? null,
            'user' => UserResource::make($this->user),
            'role' => $this->role->value,
        ];
    }
}
