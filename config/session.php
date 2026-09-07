<?php
return [
    'driver' => getenv('SESSION_DRIVER') ?: 'file',
    'lifetime_minutes' => (int)(getenv('SESSION_LIFETIME') ?: 120),
    'table' => 'sessions',
];
