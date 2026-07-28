<?php

namespace App\Models;

use VitaminD\Core\Models\PersonalAccessToken as CorePersonalAccessToken;
use VitaminD\Plugins\Workspace\Concerns\HasWorkspaceScopedTokens;

class PersonalAccessToken extends CorePersonalAccessToken
{
    use HasWorkspaceScopedTokens;
}
