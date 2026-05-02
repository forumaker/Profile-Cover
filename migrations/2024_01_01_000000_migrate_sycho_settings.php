<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $keys = ['max_size', 'thumbnails'];

        foreach ($keys as $key) {
            $oldKey = 'sycho-profile-cover.' . $key;
            $newKey = 'forumaker-profile-cover.' . $key;

            $exists = $db->table('settings')->where('key', $newKey)->exists();
            if ($exists) continue;

            $old = $db->table('settings')->where('key', $oldKey)->value('value');
            if ($old !== null) {
                $db->table('settings')->insert(['key' => $newKey, 'value' => $old]);
            }
        }
    },

    'down' => function (Builder $schema) {},
];