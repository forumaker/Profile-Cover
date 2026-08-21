<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'cover_position')) {
            $schema->table('users', function ($table) {
                $table->unsignedTinyInteger('cover_position')->nullable();
            });
        }
    },

    'down' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'cover_position')) {
            $schema->table('users', function ($table) {
                $table->dropColumn('cover_position');
            });
        }
    },
];
