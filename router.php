<?php
// Fase 1: front-controller di public/index.php, router.php jadi BC shim.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/api/v1/') || $path === '/' || preg_match('#^/u/#', $path)) {
    require __DIR__ . '/public/index.php';
    exit;
}
$file = __DIR__ . $path;
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
