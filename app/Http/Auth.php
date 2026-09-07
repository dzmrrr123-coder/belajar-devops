<?php
namespace App\Http;
class Auth {
    public static function loggedIn(): bool { return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); }
    public static function isAdmin(\mysqli $conn, int $uid): bool {
        try {
            if (\App\Domain\Auth\Roles::isAdmin($conn, $uid)) return true;
            return self::isOwner($conn, $uid);
        } catch (\Throwable $e) { return false; }
    }
    public static function isOwner(\mysqli $conn, int $uid): bool {
        try {
            $s = $conn->prepare("SELECT email FROM users WHERE id=?");
            if (!$s) return false;
            $s->bind_param("i",$uid); $s->execute();
            $r = $s->get_result()->fetch_assoc(); $s->close();
            $owner = defined('OWNER_ADMIN_EMAIL') ? OWNER_ADMIN_EMAIL : 'dzmrrr123@gmail.com';
            return strtolower(trim((string)($r['email']??''))) === strtolower(trim($owner));
        } catch (\Throwable $e) { return false; }
    }
    public static function requireLogin(): void {
        if (!self::loggedIn()) { Flash::set('warning','Silakan login terlebih dahulu untuk melanjutkan.'); header("Location: login.php"); exit(); }
    }
    public static function requireAdmin(\mysqli $conn): void {
        if (!self::loggedIn() || !self::isAdmin($conn,(int)($_SESSION['user_id']??0))) {
            http_response_code(404); require dirname(__DIR__,2).'/404.php'; exit();
        }
    }
    public static function redirect(string $url): void { header("Location: $url"); exit(); }
}
