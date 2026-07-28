<?php

namespace VitaminD\Core\Models;

use VitaminD\Core\Enums\PluginSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $version
 * @property string|null $description
 * @property string|null $repo
 * @property string $namespace
 * @property PluginSource $source
 * @property bool $is_enabled
 * @property bool $is_installed
 * @property ?Carbon $installed_at
 * @property bool $updates_available
 * @property string $folder
 * @property string $username
 * @property Collection<int, PluginError> $errors
 */
class Plugin extends Model
{
    protected $fillable = [
        'name',
        'version',
        'description',
        'repo',
        'namespace',
        'source',
        'is_enabled',
        'is_installed',
        'installed_at',
        'updates_available',
        'folder',
        'username',
    ];

    protected $casts = [
        'source' => PluginSource::class,
        'is_enabled' => 'boolean',
        'is_installed' => 'boolean',
        'installed_at' => 'datetime',
        'updates_available' => 'boolean',
    ];

    public function errors(): HasMany
    {
        return $this->hasMany(PluginError::class);
    }
}
