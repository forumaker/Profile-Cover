<?php

namespace Forumaker\ProfileCover;

use Flarum\Foundation\AbstractImageValidator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Validation\Factory;
use Intervention\Image\ImageManager;

class CoverValidator extends AbstractImageValidator
{
    public function __construct(
        Factory $validator,
        TranslatorInterface $translator,
        ImageManager $imageManager,
        protected SettingsRepositoryInterface $config
    ) {
        parent::__construct($validator, $translator, $imageManager);
    }

    public function assertValid(array $attributes): void
    {
        // Deliberately just sets $this->filename and defers to the parent
        // implementation, rather than re-listing the assert* calls here:
        // AbstractImageValidator::assertValid() checks file size *before*
        // mimes (which decodes the image) specifically so an oversized
        // upload is rejected before ever being read into memory — a local
        // reimplementation had this order backwards.
        $this->filename = 'cover';

        parent::assertValid($attributes);
    }

    public function getMaxSize(): int
    {
        return (int) ($this->config->get('forumaker-profile-cover.max_size') ?? 2048);
    }

    protected function getAllowedTypes(): array
    {
        return ['jpeg', 'jpg', 'png', 'bmp', 'gif', 'webp'];
    }
}
