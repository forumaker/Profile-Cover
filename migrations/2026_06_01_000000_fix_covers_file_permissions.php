<?php

use Illuminate\Database\Schema\Builder;

// Intentionally a no-op: the file permission fix this migration was meant to apply
// is now handled by the Extend\Filesystem disk permissions closure in extend.php.
// Kept in place (rather than deleted) so the migrations table doesn't drift for
// installs that already ran it.
return [
    'up'   => function (Builder $schema) {},
    'down' => function (Builder $schema) {},
];
