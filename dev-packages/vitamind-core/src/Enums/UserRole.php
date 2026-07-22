<?php

namespace VitaminD\Core\Enums;

use VitaminD\Core\Contracts\AppEnum;

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
