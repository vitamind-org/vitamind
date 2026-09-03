<?php

namespace VitaminD\Plugins\Archive\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use VitaminD\Core\Models\AbstractModel;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?int $parent_id
 * @property int $owner_id
 * @property ?int $workspace_id
 * @property string $visibility
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Folder $parent
 */
class Folder extends AbstractModel
{
    use SoftDeletes;

    // owner_id/workspace_id are deliberately excluded and set explicitly by
    // the controller — see design.md D4.
    protected $fillable = [
        'name',
        'parent_id',
        'visibility',
    ];

    protected static function booted(): void
    {
        static::creating(function (Folder $folder): void {
            $folder->uuid ??= (string) Str::uuid();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'owner_id');
    }
}
