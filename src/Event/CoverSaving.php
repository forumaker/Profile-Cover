<?php

namespace Forumaker\ProfileCover\Event;

use Flarum\User\User;
use Intervention\Image\Image;

class CoverSaving
{
    public function __construct(
        public readonly User $user,
        public readonly User $actor,
        public readonly Image $image
    ) {
    }
}
