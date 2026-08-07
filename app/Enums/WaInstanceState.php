<?php

namespace App\Enums;

enum WaInstanceState: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Unhealthy = 'unhealthy';
    case Failed = 'failed';
}
