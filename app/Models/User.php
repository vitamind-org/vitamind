<?php

namespace App\Models;

use VitaminD\Core\Models\User as CoreUser;
use VitaminD\Plugins\Workspace\Concerns\HasWorkspaces;

class User extends CoreUser
{
    use HasWorkspaces;
}
