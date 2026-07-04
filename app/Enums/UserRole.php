<?php

namespace App\Enums;

use App\Contracts\AppEnum;

enum UserRole: string implements AppEnum
{
    case USER = 'user';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    public function getColor(): string
    {
        return 'default';
    }

    public function getText(): string
    {
        return $this->value;
    }
}
