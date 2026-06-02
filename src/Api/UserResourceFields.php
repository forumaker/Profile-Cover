<?php

namespace Forumaker\ProfileCover\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;

class UserResourceFields
{
    protected Filesystem $coversDir;

    public function __construct(
        private SettingsRepositoryInterface $settings,
        Factory $filesystem
    ) {
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

    private function thumbnailUrl(?string $imageName): ?string
    {
        if (empty($imageName)) {
            return null;
        }

        if (str_ends_with(strtolower($imageName), '.gif')) {
            return $this->coversDir->url($imageName);
        }

        if ($this->settings->get('forumaker-profile-cover.thumbnails', 0) == 1) {
            return $this->coversDir->url('thumbnails/' . $imageName);
        }

        return $this->coversDir->url($imageName);
    }
}
