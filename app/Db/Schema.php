<?php
namespace App\Db;
class Schema {
    public static function expected(): int {
        return defined('SCHEMA_VERSION') ? (int)SCHEMA_VERSION : 32;
    }
    public static function current(\mysqli $conn): ?int {
        try {
            mysqli_report(MYSQLI_REPORT_OFF);
            $r = $conn->query("SELECT `v` FROM `schema_meta` WHERE `k` = 'version' LIMIT 1");
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            if (!$r) return null;
            $row = $r->fetch_assoc(); $r->free();
            return isset($row['v']) ? (int)$row['v'] : null;
        } catch (\Throwable $e) {
            try { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); } catch (\Throwable $e2) {}
            return null;
        }
    }
    public static function needsUpgrade(\mysqli $conn): bool {
        $cur = self::current($conn);
        return $cur === null || $cur < self::expected();
    }
    public static function autoMigrateEnabled(): bool {
        $v = getenv('DB_AUTO_MIGRATE');
        return $v === false || $v === '' || $v === '1' || strtolower((string)$v) === 'true';
    }
}
