<?php

namespace App\Models;

use App\Enums\WaNumberStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VitaminD\Core\Models\AbstractModel;
use VitaminD\PluginSdk\Concerns\BelongsToWorkspace;

class WaNumber extends AbstractModel
{
    use BelongsToWorkspace;

    // `workspace_id` is intentionally not fillable: BelongsToWorkspace
    // stamps it directly, so a crafted request body can't place a number
    // into another workspace.
    protected $fillable = [
        'wa_instance_id',
        'device_id',
        'phone_number',
        'jid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => WaNumberStatus::class,
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WaInstance::class, 'wa_instance_id');
    }
}
