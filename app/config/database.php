<?php

declare(strict_types=1);

return [
    'default' => [
        'driver'   => 'pgsql',
        'host'     => 'db',
        'port'     => 5432,
        'database' => 'webl',
        'username' => 'webl',
    ],
    'local' => [
        'driver'   => 'pgsql',
        'host'     => 'db',
        'port'     => 5432,
        'database' => 'webl',
        'username' => 'webl',
    ],
    'prod' => [
        'debug_sql' => false,
    ],
];
