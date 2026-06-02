<?php

namespace Forumaker\ProfileCover\Api;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Bus\Dispatcher;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\Arr;
use Forumaker\ProfileCover\Command\DeleteCover;
use Forumaker\ProfileCover\Command\UploadCover;
use Psr\Http\Message\UploadedFileInterface;

class UserResourceEndpoints
{
    public function __construct(protected Dispatcher $bus)
    {
    }

    public function __invoke(): array
    {
        return [
            Endpoint\Endpoint::make('cover.upload')
                ->route('POST', '/{id}/cover')
                ->action(function (Context $context) {
                    $file = Arr::get($context->request->getUploadedFiles(), 'cover');

                    if (!$file instanceof UploadedFileInterface) {
                        throw new ValidationException(['cover' => ['No file was uploaded.']]);
                    }

                    return $this->bus->dispatch(
                        new UploadCover($context->modelId, $file, $context->getActor())
                    );
                }),
            Endpoint\Endpoint::make('cover.delete')
                ->route('DELETE', '/{id}/cover')
                ->action(function (Context $context) {
                    return $this->bus->dispatch(
                        new DeleteCover($context->modelId, $context->getActor())
                    );
                }),
        ];
    }
}
