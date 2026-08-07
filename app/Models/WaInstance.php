<?php

namespace App\Models;

use App\Enums\WaInstanceState;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use VitaminD\Core\Models\AbstractModel;
use VitaminD\Plugins\Workspace\Models\Workspace;

class WaInstance extends AbstractModel
{
    protected $fillable = [
        'workspace_id',
        'base_url',
        'basic_auth_username',
        'basic_auth_password',
        'webhook_secret',
        'state',
    ];

    protected $hidden = [
        'basic_auth_password',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'basic_auth_password' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'state' => WaInstanceState::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function numbers(): HasMany
    {
        return $this->hasMany(WaNumber::class);
    }
}
