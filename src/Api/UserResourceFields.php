<?php

namespace Forumaker\ProfileCover\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Foundation\Paths;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;

class UserResourceFields
{
    protected Filesystem $coversDir;

    public function __construct(protected Paths $paths, Factory $filesystem)
    {
        $this->coversDir = $filesystem->disk('forumaker-profile-cover');
    }

    public function __invoke(): array
    {
        return [
            Schema\Str::make('cover')
                ->get(fn (User $user) => $user->cover ? $this->coversDir->url($user->cover) : $user->cover),
            Schema\Str::make('cover_thumbnail')
                ->get(fn (User $user) => $this->thumbnailUrl($user->cover)),
            Schema\Boolean::make('canSetProfileCover')
                ->get(fn (User $user, Context $context) => $context->getActor()->can('setProfileCover', $user)),
        ];
    }

    public function thumbnailUrl(?string $imageName): ?string
    {
        if (empty($imageName)) {
            return null;
        }

        // GIFs have no thumbnail — return cover URL directly
        if (str_ends_with(strtolower($imageName), '.gif')) {
            return $this->coversDir->url($imageName);
        }

        $thumbnailName = 'thumbnails/' . $imageName;

        if ($this->coversDir->exists($thumbnailName)) {
            return $this->coversDir->url($thumbnailName);
        }

        // Thumbnail not generated yet — fall back to full cover
        return $this->coversDir->url($imageName);
    }
}