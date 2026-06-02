<?php

namespace Forumaker\ProfileCover;

use Illuminate\Support\Str;
use Intervention\Image\Image;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\UploadedFileInterface;

class CoverUploader
{
    protected Filesystem $coversDir;

    public function __construct(Factory $filesystem, protected SettingsRepositoryInterface $config)
    {
        $this->coversDir = $filesystem->disk('forumaker-profile-cover');
    }

    public function upload(User $user, Image $image): void
    {
        $makeThumb = $this->config->get('forumaker-profile-cover.thumbnails', 0) == 1;

        if ($image->width() > 2500) {
            $image->scale(2500);
        }

        $encodedImage = $image->toJpg();
        $coverPath    = Str::random() . '.jpg';

        $this->remove($user);
        $user->cover = $coverPath;

        if ($makeThumb) {
            $thumbWidth    = (int) $this->config->get('forumaker-profile-cover.thumbnail_width', 500);
            $thumbnail     = clone $image;
            $thumbnailPath = 'thumbnails/' . $coverPath;

            $thumbnail->scale($thumbWidth);
            $encodedThumbnail = $thumbnail->toJpg();

            $this->coversDir->put($thumbnailPath, $encodedThumbnail);
        }

        $this->coversDir->put($coverPath, $encodedImage);
    }

    public function uploadGif(User $user, UploadedFileInterface $file): void
    {
        $coverPath = Str::random() . '.gif';

        $this->remove($user);
        $user->cover = $coverPath;

        $this->coversDir->put($coverPath, $file->getStream());
    }

    public function remove(User $user): void
    {
        $coverPath = $user->cover;

        if (empty($coverPath)) {
            return;
        }

        $user->afterSave(function () use ($coverPath) {
            $thumbnailPath = 'thumbnails/' . $coverPath;

            if ($this->coversDir->exists($coverPath)) {
                $this->coversDir->delete($coverPath);
            }

            if ($this->coversDir->exists($thumbnailPath)) {
                $this->coversDir->delete($thumbnailPath);
            }
        });

        $user->cover = null;
    }
}
