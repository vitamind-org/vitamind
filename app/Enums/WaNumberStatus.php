<?php

namespace App\Enums;

enum WaNumberStatus: string
{
    case Disconnected = 'disconnected';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case LoggedIn = 'logged_in';
}
