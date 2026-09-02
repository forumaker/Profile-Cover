<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        // Raw `settings` table access instead of SettingsRepositoryInterface:
        // migrations run before the container is fully booted, so the
        // repository isn't reliably available here — direct DB access is
        // the pragmatic (if table-name-coupled) option in this context.
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