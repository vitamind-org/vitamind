<?php

namespace VitaminD\Plugins\Workspace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use VitaminD\Core\Models\User;
use VitaminD\Plugins\Workspace\Models\Workspace;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?User $user */
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $user ? ($this->owner_id === $user->id ? 'owner' : 'member') : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'users' => WorkspaceUserResource::collection($this->whenLoaded('users')),
        ];
    }
}
