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
        $this->filename = 'cover';
        $this->laravelValidator = $this->makeValidator($attributes);

        $this->assertFileRequired($attributes['cover']);
        $this->assertFileMimes($attributes['cover']);
        $this->assertFileSize($attributes['cover']);
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
