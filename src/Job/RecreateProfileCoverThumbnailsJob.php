<?php

namespace Forumaker\ProfileCover\Job;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;

class RecreateProfileCoverThumbnailsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public function handle(
        Factory $filesystem,
        ImageManager $imageManager,
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger
    ): void {
        $coversDir = $filesystem->disk('forumaker-profile-cover');
        $width = (int) $settings->get('forumaker-profile-cover.thumbnail_width', 500);

        User::whereNotNull('cover')
            ->where('cover', '!=', '')
            ->select(['cover'])
            ->chunk(100, function ($users) use ($coversDir, $imageManager, $width, $logger) {
                foreach ($users as $user) {
                    $coverPath = $user->cover;

                    if (str_ends_with(strtolower($coverPath), '.gif')) {
                        continue;
                    }

                    if (!$coversDir->exists($coverPath)) {
                        continue;
                    }

                    try {
                        $data = $coversDir->get($coverPath);
                        $image = $imageManager->read($data);
                        $image->scale($width);

                        $coversDir->put('thumbnails/' . $coverPath, $image->toJpg());
                    } catch (\Exception $e) {
                        $logger->error('forumaker-profile-cover: failed to recreate thumbnail for ' . $coverPath, [
                            'exception' => $e,
                        ]);
                    }
                }
            });
    }
}
