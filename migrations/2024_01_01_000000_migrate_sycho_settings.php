<?php

use Illuminate\Database\Schema\Builder;

/**
 * Migrate settings from sycho/flarum-profile-cover to forumaker/profile-cover.
 * Safe to run even if the old extension was never installed.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $keys = ['max_size', 'thumbnails'];

        foreach ($keys as $key) {
            $oldKey = 'sycho-profile-cover.' . $key;
            $newKey = 'forumaker-profile-cover.' . $key;

            // Skip if new key already has a value
            $exists = $db->table('settings')->where('key', $newKey)->exists();
            if ($exists) continue;

            $old = $db->table('settings')->where('key', $oldKey)->value('value');
            if ($old !== null) {
                $db->table('settings')->insert(['key' => $newKey, 'value' => $old]);
            }
        }
    },

    'down' => function (Builder $schema) {
        // Nothing to reverse — we don't delete old sycho settings
    },
];
