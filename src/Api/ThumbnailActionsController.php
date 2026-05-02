<?php

namespace Forumaker\ProfileCover\Api;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ThumbnailActionsController implements RequestHandlerInterface
{
    protected Filesystem $coversDir;

    public function __construct(
        Factory $filesystem,
        protected ImageManager $imageManager,
        protected SettingsRepositoryInterface $settings
    ) {
        $this->coversDir = $filesystem->disk('forumaker-profile-cover');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $action = basename($request->getUri()->getPath());

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
        $width  = (int) $this->settings->get('forumaker-profile-cover.thumbnail_width', 500);
        $count  = 0;
        $errors = 0;

        $users = User::whereNotNull('cover')->where('cover', '!=', '')->get(['cover']);

        foreach ($users as $user) {
            $coverPath = $user->cover;

            if (str_ends_with(strtolower($coverPath), '.gif')) {
                continue;
            }

            if (!$this->coversDir->exists($coverPath)) {
                continue;
            }

            try {
                $data      = $this->coversDir->get($coverPath);
                $image     = $this->imageManager->read($data);
                $image->scale($width);
                $thumbnail = $image->toJpg();

                $this->coversDir->put('thumbnails/' . $coverPath, $thumbnail);
                $count++;
            } catch (\Exception $e) {
                $errors++;
            }
        }

        return new JsonResponse(['recreated' => $count, 'errors' => $errors]);
    }
}