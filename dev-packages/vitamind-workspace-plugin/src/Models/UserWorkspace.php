<?php

namespace VitaminD\Plugins\Workspace\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use VitaminD\Core\Models\AbstractModel;
use VitaminD\Core\Models\User;

/**
 * @property int $id
 * @property int $workspace_id
 * @property ?int $user_id
 * @property ?string $email
 * @property ?string $invited_role
 * @property bool $is_admin_grant
 * @property bool $is_default
 * @property ?User $user
 * @property Workspace $workspace
 */
class UserWorkspace extends AbstractModel
{
    protected $table = 'user_workspace';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'email',
        'invited_role',
        'is_admin_grant',
        'is_default',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'user_id' => 'integer',
        'is_admin_grant' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Promotes the remaining membership with the earliest `created_at` to
     * default for the given user. Shared by every removal path
     * (`LeaveWorkspaceController`, `WorkspaceUserController::destroy()`) so
     * "at most one default, oldest remaining wins" stays in one place.
     *
     * Callers are expected to run the preceding membership deletion and
     * this call inside the same `DB::transaction()` — the `lockForUpdate()`
     * here only holds for the life of that transaction, and is what
     * prevents a concurrent workspace creation or invitation acceptance
     * from assigning a second default between the delete and this read.
     */
    public static function promoteOldestDefaultFor(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            /** @var ?self $next */
            $next = static::query()
                ->where('user_id', $userId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $next?->update(['is_default' => true]);
        });
    }
}
