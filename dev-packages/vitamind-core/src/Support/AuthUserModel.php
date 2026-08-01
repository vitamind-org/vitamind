<?php

namespace VitaminD\Core\Support;

if (! function_exists(__NAMESPACE__.'\authUserModel')) {
    /**
     * Resolves the concrete, application-configured User model class —
     * the class core must instantiate/query against instead of hardcoding
     * `App\Models\User`, so app-layer mixins (e.g. HasWorkspaces) are
     * preserved on the created/queried instance.
     *
     * @return class-string<\VitaminD\Core\Models\User>
     */
    function authUserModel(): string
    {
        return config('auth.providers.users.model');
    }
}
