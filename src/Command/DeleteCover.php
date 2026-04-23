<?php

namespace Forumaker\ProfileCover\Command;

use Flarum\User\User;

class DeleteCover
{
    public function __construct(
        public readonly int $userId,
        public readonly User $actor
    ) {
    }
}
