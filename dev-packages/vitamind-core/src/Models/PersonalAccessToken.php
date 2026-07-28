<?php

namespace VitaminD\Core\Models;

use VitaminD\Core\Traits\HasTimezoneTimestamps;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<string> $abilities
 * @property Carbon $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasTimezoneTimestamps;
}
