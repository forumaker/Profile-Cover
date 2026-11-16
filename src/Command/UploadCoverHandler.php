<?php

namespace Forumaker\ProfileCover\Command;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Forumaker\ProfileCover\CoverImageLimits;
use Forumaker\ProfileCover\CoverUploader;
use Forumaker\ProfileCover\CoverValidator;
use Forumaker\ProfileCover\Event\CoverSaving;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\User\UserRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;

class UploadCoverHandler
{
    use DispatchEventsTrait;

    public function __construct(
        protected Dispatcher $events,
        protected UserRepository $users,
        protected CoverUploader $uploader,
        protected CoverValidator $validator,
        protected ImageManager $imageManager,
        protected LoggerInterface $logger
    ) {
    }

    public function handle(UploadCover $command): User
    {
        $actor = $command->actor;
        $user  = $this->users->findOrFail($command->userId);

        $actor->assertCan('setProfileCover', $user);

        $this->validator->assertValid(['cover' => $command->file]);

        $filePath = $command->file->getStream()->getMetadata('uri');
        $mimeType = (new MimeTypes())->guessMimeType($filePath);

        $isGif = $mimeType === 'image/gif';

        if ($isGif && $command->crop === null) {
            $this->uploader->uploadGif($user, $command->file);
        } else {
            $this->assertDimensionsWithinLimit($filePath);

            try {
                $image = $this->imageManager->read($filePath);

                if ($command->crop !== null) {
                    $this->crop($image, $command->crop);
                }

                $this->events->dispatch(
                    new CoverSaving($user, $actor, $image)
                );

                $this->uploader->upload($user, $image, $isGif);
                if ($command->crop !== null) {
                    $user->cover_position = 50;
                }
            } catch (\Intervention\Image\Exceptions\DecoderException $e) {
                throw new ValidationException(['cover' => ['The uploaded image file is corrupted or unreadable.']]);
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logger->error('forumaker-profile-cover: cover upload failed', ['exception' => $e]);

                throw new ValidationException(['cover' => ['The uploaded file could not be processed.']]);
            }
        }

        $user->save();

        $this->dispatchEventsFor($user, $actor);

        return $user;
    }

    private function crop(ImageInterface $image, string $value): void
    {
        $area = json_decode($value, true, 4);
        if (is_array($area)) {
            $x = $area['x'] ?? null;
            $y = $area['y'] ?? null;
            $width = $area['width'] ?? null;
            $height = $area['height'] ?? null;

            if (is_int($x) && is_int($y) && is_int($width) && is_int($height)
                && $x >= 0 && $y >= 0 && $width > 0 && $height > 0
                && $width <= $image->width() - $x && $height <= $image->height() - $y) {
                $image->crop($width, $height, $x, $y, 'transparent');

                return;
            }
        }

        throw new ValidationException(['cover' => ['Invalid crop area.']]);
    }

    /**
     * Reads only the image header (not the full pixel buffer) to reject
     * oversized images before Intervention Image decodes them into memory.
     *
     * A file getimagesize() can't read the dimensions of is rejected rather
     * than let through: every non-GIF type this handler still allows past
     * CoverValidator (jpeg/png/bmp/webp) is one getimagesize() reliably
     * parses, so a `false` here means either a corrupt file or one crafted
     * to confuse the header parser — either way, not something we want
     * handed to Intervention Image's decoder unchecked.
     */
    private function assertDimensionsWithinLimit(string $filePath): void
    {
        $dimensions = @getimagesize($filePath);

        if ($dimensions === false) {
            throw new ValidationException(['cover' => ['The uploaded image file is corrupted or unreadable.']]);
        }

        [$width, $height] = $dimensions;

        if ($width > CoverImageLimits::MAX_IMAGE_DIMENSION || $height > CoverImageLimits::MAX_IMAGE_DIMENSION) {
            throw new ValidationException([
                'cover' => ['The uploaded image dimensions are too large (maximum ' . CoverImageLimits::MAX_IMAGE_DIMENSION . 'px per side).'],
            ]);
        }
    }
}
