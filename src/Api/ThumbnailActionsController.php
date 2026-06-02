<?php

namespace Forumaker\ProfileCover\Api;

use Flarum\Http\RequestUtil;
use Forumaker\ProfileCover\Job\RecreateProfileCoverThumbnailsJob;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class ThumbnailActionsController implements RequestHandlerInterface
{
    protected Filesystem $coversDir;

    public function __construct(
        Factory $filesystem,
        protected Dispatcher $bus,
        protected LoggerInterface $logger
    ) {
        $this->coversDir = $filesystem->disk('forumaker-profile-cover');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $action = $request->getAttribute('routeParameters')['action'] ?? '';

        return match ($action) {
            'delete'   => $this->deleteThumbnails(),
            'recreate' => $this->recreateThumbnails(),
            default    => new JsonResponse(['error' => 'Unknown action'], 400),
        };
    }

    private function deleteThumbnails(): ResponseInterface
    {
        try {
            $files = $this->coversDir->files('thumbnails');
        } catch (\Exception $e) {
            $this->logger->warning('forumaker-profile-cover: failed to list thumbnail files', [
                'exception' => $e,
            ]);
            $files = [];
        }

        $count = 0;
        foreach ($files as $file) {
            $this->coversDir->delete($file);
            $count++;
        }

        return new JsonResponse(['deleted' => $count]);
    }

    private function recreateThumbnails(): ResponseInterface
    {
        $this->bus->dispatch(new RecreateProfileCoverThumbnailsJob());

        return new JsonResponse(['queued' => true], 202);
    }
}
