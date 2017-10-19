<?php

namespace App\Modules\Settings\Policies;

use App\Modules\Users\Models\User;
use App\Modules\Settings\Models\Option;

use Nova\Auth\Access\HandlesAuthorizationTrait;


class OptionPolicy
{
    use HandlesAuthorizationTrait;


    /**
     * Determine whether the user can manage the permissions.
     *
     * @param  \App\Models\User  $authUser
     * @return mixed
     */
    public function manage(User $authUser)
    {
        return $authUser->hasPermission('module.settings.manage');
    }
}
