<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path === '/') {
    require __DIR__ . '/index.php';
    exit;
}

if (preg_match('#^/u/([a-zA-Z0-9_]+)/?$#', $path, $m)) {
    $_GET['u'] = $m[1];
    require __DIR__ . '/u.php';
    exit;
}

if (is_file($file)) {
    if (str_ends_with($file, '.php')) {
        require $file;
    } else {
        return false;
    }
    exit;
}

http_response_code(404);
require __DIR__ . '/404.php';
