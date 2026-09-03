<?php

namespace VitaminD\Plugins\Archive\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use VitaminD\Core\Models\AbstractModel;

/**
 * @property int $id
 * @property string $uuid
 * @property string $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property ?int $folder_id
 * @property int $owner_id
 * @property ?int $workspace_id
 * @property string $visibility
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class File extends AbstractModel
{
    use SoftDeletes;

    // owner_id/workspace_id are deliberately excluded and set explicitly by
    // the controller — see design.md D4.
    protected $fillable = [
        'original_name',
        'extension',
        'mime_type',
        'size',
        'disk',
        'path',
        'folder_id',
        'visibility',
    ];

    protected static function booted(): void
    {
        static::creating(function (File $file): void {
            $file->uuid ??= (string) Str::uuid();
        });

        // Physical removal only happens on a *force* delete — a regular
        // delete() just soft-deletes the row and leaves the underlying
        // object in place (design.md D10).
        static::deleting(function (File $file): void {
            if ($file->isForceDeleting()) {
                Storage::disk($file->disk)->delete($file->path);
            }
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'owner_id');
    }
}
