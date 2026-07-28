<?php

namespace VitaminD\Core\Enums;

use VitaminD\Core\Contracts\AppEnum;

enum PluginSource: string implements AppEnum
{
    case LOCAL = 'local';
    case GITHUB = 'github';
    case COMPOSER = 'composer';

    public function getColor(): string
    {
        return match ($this) {
            self::LOCAL => 'default',
            self::GITHUB => 'info',
            self::COMPOSER => 'success',
        };
    }

    public function getText(): string
    {
        return match ($this) {
            self::LOCAL => 'Local',
            self::GITHUB => 'GitHub',
            self::COMPOSER => 'Composer',
        };
    }
}
