<?php
namespace App\Http;
class Csrf {
    public static function token(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    public static function field(): string { return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(self::token()).'">'; }
    public static function check(string $token): bool { return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token); }
    public static function verify(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !self::check($_POST['csrf_token'] ?? '')) {
            http_response_code(403); exit('Error 403: Invalid CSRF Token request.');
        }
    }
}
