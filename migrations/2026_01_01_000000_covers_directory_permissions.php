<?php

use Flarum\Foundation\Paths;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $paths     = resolve(Paths::class);
        $coversDir = $paths->public . '/assets/covers';

        foreach ([$coversDir, $coversDir . '/thumbnails'] as $dir) {
            if (is_dir($dir)) {
                chmod($dir, 0755);
            }
        }
    },

    'down' => function (Builder $schema) {},
];