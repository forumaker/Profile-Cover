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
use Symfony\Component\Mime\MimeTypes;

class UploadCoverHandler
{
    use DispatchEventsTrait;

    public function __construct(
        protected Dispatcher $events,
        protected UserRepository $users,
        protected CoverUploader $uploader,
        protected CoverValidator $validator,
        protected ImageManager $imageManager
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
            try {
                $image = $this->imageManager->read($filePath);

                $this->events->dispatch(
                    new CoverSaving($user, $actor, $image)
                );

                $this->uploader->upload($user, $image);
            } catch (\Intervention\Image\Exceptions\DecoderException $e) {
                throw new ValidationException(['cover' => ['The uploaded image file is corrupted or unreadable.']]);
            } catch (\Exception $e) {
                throw new ValidationException(['cover' => ['The uploaded file could not be processed.']]);
            }
        }

        $user->save();

        $this->dispatchEventsFor($user, $actor);

        return $user;
    }
}
