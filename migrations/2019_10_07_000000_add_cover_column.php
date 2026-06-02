<?php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'cover' => ['string', 'nullable' => true, 'length' => 150],
]);
