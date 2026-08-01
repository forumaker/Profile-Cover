<?php

namespace Forumaker\ProfileCover\Command;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Forumaker\ProfileCover\CoverUploader;
use Forumaker\ProfileCover\CoverValidator;
use Forumaker\ProfileCover\Event\CoverSaving;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\User\UserRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;

class UploadCoverHandler
{
    use DispatchEventsTrait;

    /**
     * Maximum allowed width/height (in pixels) for an uploaded cover image.
     * Guards against decoding pathologically large images into memory
     * (a maximally-compressed image can have a huge pixel buffer despite a small file size).
     */
    protected const MAX_IMAGE_DIMENSION = 10000;

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

        if ($mimeType === 'image/gif') {
            $this->uploader->uploadGif($user, $command->file);
        } else {
            $this->assertDimensionsWithinLimit($filePath);

            try {
                $image = $this->imageManager->read($filePath);

                $this->events->dispatch(
                    new CoverSaving($user, $actor, $image)
                );

                $this->uploader->upload($user, $image);
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

    /**
     * Reads only the image header (not the full pixel buffer) to reject
     * oversized images before Intervention Image decodes them into memory.
     */
    private function assertDimensionsWithinLimit(string $filePath): void
    {
        $dimensions = @getimagesize($filePath);

        if ($dimensions === false) {
            return;
        }

        [$width, $height] = $dimensions;

        if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION) {
            throw new ValidationException([
                'cover' => ['The uploaded image dimensions are too large (maximum ' . self::MAX_IMAGE_DIMENSION . 'px per side).'],
            ]);
        }
    }
}
