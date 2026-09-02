<?php

namespace Forumaker\ProfileCover\Job;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Forumaker\ProfileCover\CoverImageLimits;
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
        // 1px floor: an admin clearing/zeroing the setting would otherwise
        // reach Image::scale(0), which throws and silently fails every
        // single user's thumbnail (see CoverUploader::upload(), which has
        // the same floor for the same reason).
        $width = max(1, (int) $settings->get('forumaker-profile-cover.thumbnail_width', 500));

        // Ordered by id: chunk() pages via LIMIT/OFFSET, so without a
        // stable order a cover added/removed by another request mid-run
        // can shift rows across the page boundary and get skipped or
        // processed twice.
        User::whereNotNull('cover')
            ->where('cover', '!=', '')
            ->select(['id', 'cover'])
            ->orderBy('id')
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

                        // Unlike a fresh upload (see UploadCoverHandler::
                        // assertDimensionsWithinLimit()), a cover already on
                        // disk was never guaranteed to pass today's
                        // dimension limit — it may predate the check, or
                        // have been placed there directly. Apply the same
                        // guard here before decoding, so a pathologically
                        // large cover can't blow up the queue worker's
                        // memory for every chunk this job runs.
                        $this->assertDimensionsWithinLimit($data, $coverPath);

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

    /**
     * @throws \RuntimeException if the image can't be read or exceeds
     *                            CoverImageLimits::MAX_IMAGE_DIMENSION
     */
    private function assertDimensionsWithinLimit(string $data, string $coverPath): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'profile-cover-');

        try {
            file_put_contents($tmpFile, $data);
            $dimensions = @getimagesize($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        if ($dimensions === false) {
            throw new \RuntimeException("Could not determine image dimensions for {$coverPath}");
        }

        [$width, $height] = $dimensions;

        if ($width > CoverImageLimits::MAX_IMAGE_DIMENSION || $height > CoverImageLimits::MAX_IMAGE_DIMENSION) {
            throw new \RuntimeException(
                "Cover {$coverPath} exceeds the maximum dimension of " . CoverImageLimits::MAX_IMAGE_DIMENSION . 'px per side'
            );
        }
    }
}
