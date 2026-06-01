<?php

use Flarum\Foundation\Paths;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $paths     = app(Paths::class);
        $coversDir = $paths->public . '/assets/covers';

        foreach ([$coversDir, $coversDir . '/thumbnails'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            chmod($dir, 0755);

            foreach (new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS) as $file) {
                if ($file->isFile()) {
                    chmod($file->getPathname(), 0644);
                }
            }
        }
    },

    'down' => function (Builder $schema) {},
];
