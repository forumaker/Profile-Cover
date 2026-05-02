<?php

use Flarum\Extend;
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Forumaker\ProfileCover\Access\UserPolicy;
use Forumaker\ProfileCover\Api\ThumbnailActionsController;
use Forumaker\ProfileCover\Api\UserResourceEndpoints;
use Forumaker\ProfileCover\Api\UserResourceFields;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Model(User::class))
        ->cast('cover', 'string'),

    (new Extend\Settings())
        ->serializeToForum('forumaker-profile-cover.max_size', 'forumaker-profile-cover.max_size'),

    (new Extend\ApiResource(Flarum\Api\Resource\UserResource::class))
        ->fields(UserResourceFields::class)
        ->endpoints(UserResourceEndpoints::class),

    (new Extend\Policy())
        ->modelPolicy(User::class, UserPolicy::class),

    (new Extend\Filesystem())
        ->disk('forumaker-profile-cover', function (Paths $paths, UrlGenerator $url) {
            return [
                'root'        => "$paths->public/assets/covers",
                'url'         => $url->to('forum')->path('assets/covers'),
                'permissions' => [
                    'dir'  => ['public' => 0755, 'private' => 0700],
                    'file' => ['public' => 0644, 'private' => 0600],
                ],
            ];
        }),

    (new Extend\Routes('api'))
        ->post(
            '/forumaker-profile-cover/thumbnails/{action}',
            'forumaker-profile-cover.thumbnails',
            ThumbnailActionsController::class
        ),
];