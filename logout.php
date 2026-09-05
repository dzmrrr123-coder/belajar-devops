<?php
require_once 'config.php';

try {
    clear_remember_token(db_connect());
} catch (Throwable $e) {
    clear_remember_token();
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session for flash message
session_start();
set_flash('info', 'Kamu berhasil logout. Sampai jumpa lagi di sesi belajar berikutnya! 👋');

redirect('login.php');
