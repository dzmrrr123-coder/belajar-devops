<?php
return [
    'host' => getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost'),
    'port' => (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306)),
    'user' => getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'),
    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'name' => getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'railway'),
];
