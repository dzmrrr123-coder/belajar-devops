<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') !== 0) return;
    $rel = str_replace('\\', '/', substr($class, 4));
    $file = __DIR__ . '/' . $rel . '.php';
    if (is_file($file)) require_once $file;
});
