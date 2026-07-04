<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'cover')) {
            $schema->table('users', function ($table) {
                $table->string('cover', 150)->nullable();
            });
        }
    },

    'down' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'cover')) {
            $schema->table('users', function ($table) {
                $table->dropColumn('cover');
            });
        }
    },
];
