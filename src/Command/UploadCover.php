<?php

namespace Forumaker\ProfileCover\Command;

use Flarum\User\User;
use Psr\Http\Message\UploadedFileInterface;

class UploadCover
{
    public function __construct(
        public readonly int $userId,
        public readonly UploadedFileInterface $file,
        public readonly User $actor
    ) {
    }
}
