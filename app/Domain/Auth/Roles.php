<?php
namespace App\Domain\Auth;
class Roles {
    private static array $cache = [];
    public static function reset(): void { self::$cache = []; }
    public static function tablesExist(\mysqli $conn): bool {
        try {
            $r = $conn->query("SHOW TABLES LIKE 'user_roles'");
            $a = $r && $r->num_rows > 0;
            if ($r) $r->free();
            if (!$a) return false;
            $r = $conn->query("SHOW TABLES LIKE 'roles'");
            $b = $r && $r->num_rows > 0;
            if ($r) $r->free();
            return $b;
        } catch (\Throwable $e) { return false; }
    }
    public static function slugs(\mysqli $conn, int $uid): array {
        if (isset(self::$cache[$uid])) return self::$cache[$uid];
        $out = [];
        try {
            if (!self::tablesExist($conn)) return self::$cache[$uid] = $out;
            $s = $conn->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?");
            if (!$s) return self::$cache[$uid] = $out;
            $s->bind_param("i", $uid); $s->execute();
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $out[] = (string)$row['slug'];
            $s->close();
        } catch (\Throwable $e) {}
        return self::$cache[$uid] = $out;
    }
    public static function hasRole(\mysqli $conn, int $uid, string $slug): bool {
        return in_array($slug, self::slugs($conn, $uid), true);
    }
    public static function isAdmin(\mysqli $conn, int $uid): bool {
        return self::hasRole($conn, $uid, 'admin');
    }
    public static function isGuru(\mysqli $conn, int $uid): bool {
        return self::hasRole($conn, $uid, 'guru');
    }
    public static function countAdmins(\mysqli $conn): int {
        try {
            if (!self::tablesExist($conn)) return 0;
            $r = $conn->query("SELECT COUNT(*) n FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'admin'");
            if (!$r) return 0;
            $n = (int)($r->fetch_assoc()['n'] ?? 0); $r->free();
            return $n;
        } catch (\Throwable $e) { return 0; }
    }
    public static function grant(\mysqli $conn, int $uid, string $slug): bool {
        try {
            if (!self::tablesExist($conn)) return false;
            $s = $conn->prepare("SELECT id FROM roles WHERE slug = ?");
            if (!$s) return false;
            $s->bind_param("s", $slug); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            if (!$row) return false;
            $rid = (int)$row['id'];
            $ins = $conn->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
            if (!$ins) return false;
            $ins->bind_param("ii", $uid, $rid); $ok = $ins->execute(); $ins->close();
            unset(self::$cache[$uid]);
            $up = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($up) { $up->bind_param("si", $slug, $uid); $up->execute(); $up->close(); }
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function revoke(\mysqli $conn, int $uid, string $slug): bool {
        try {
            if (!self::tablesExist($conn)) return false;
            if ($slug === 'admin' && self::isAdmin($conn, $uid) && self::countAdmins($conn) <= 1) return false;
            $s = $conn->prepare("DELETE ur FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = ?");
            if (!$s) return false;
            $s->bind_param("is", $uid, $slug); $ok = $s->execute(); $s->close();
            unset(self::$cache[$uid]);
            if ($slug === 'admin') {
                $up = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                if ($up) { $up->bind_param("i", $uid); $up->execute(); $up->close(); }
            }
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
}
