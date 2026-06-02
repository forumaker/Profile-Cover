<?php

namespace Forumaker\ProfileCover\Access;

use Flarum\User\User;
use Flarum\User\Access\AbstractPolicy;

class UserPolicy extends AbstractPolicy
{
    public function setProfileCover(User $actor, User $user)
    {
        if ($actor->hasPermission('setProfileCover')
            && ($actor->id === $user->id || $actor->can('edit', $user))
        ) {
            return $this->allow();
        }

        return $this->deny();
    }
}
