<?php
namespace App\Db;
class Migrator {
    public static function migrationsDir(): string {
        return dirname(__DIR__, 2) . '/db/migrations';
    }
    public static function run(\mysqli $conn): int {
        $lock = self::lock();
        try {
            if (function_exists('ensure_database_schema')) ensure_database_schema($conn);
            self::runSqlFiles($conn);
            return (int)(Schema::current($conn) ?? Schema::expected());
        } finally {
            self::unlock($lock);
        }
    }
    public static function check(\mysqli $conn): array {
        $cur = Schema::current($conn);
        $pending = self::pendingFiles($conn);
        return ['current' => $cur, 'expected' => Schema::expected(), 'needsUpgrade' => $cur === null || $cur < Schema::expected() || count($pending) > 0, 'pendingFiles' => $pending];
    }
    private static function lock() {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $f = @fopen($dir . '/migrate.lock', 'c');
        if ($f) @flock($f, LOCK_EX);
        return $f;
    }
    private static function unlock($f): void {
        if ($f) { @flock($f, LOCK_UN); @fclose($f); }
    }
    private static function ensureTrackingTable(\mysqli $conn): void {
        @$conn->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (`file` VARCHAR(128) PRIMARY KEY, `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    private static function appliedFiles(\mysqli $conn): array {
        self::ensureTrackingTable($conn);
        try {
            $r = $conn->query("SELECT `file` FROM `schema_migrations`");
            if (!$r) return [];
            $out = [];
            while ($row = $r->fetch_assoc()) $out[$row['file']] = true;
            $r->free();
            return $out;
        } catch (\Throwable $e) { return []; }
    }
    private static function pendingFiles(\mysqli $conn): array {
        $dir = self::migrationsDir();
        if (!is_dir($dir)) return [];
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        $applied = self::appliedFiles($conn);
        $out = [];
        foreach ($files as $f) {
            $base = basename($f);
            if (!isset($applied[$base])) $out[] = $base;
        }
        return $out;
    }
    private static function runSqlFiles(\mysqli $conn): void {
        $dir = self::migrationsDir();
        if (!is_dir($dir)) return;
        $applied = self::appliedFiles($conn);
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $f) {
            $base = basename($f);
            if (isset($applied[$base])) continue;
            $sql = file_get_contents($f);
            if ($sql === false || trim($sql) === '') continue;
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $st) {
                if ($st === '' || strpos($st, '--') === 0) continue;
                @$conn->query($st);
            }
            try {
                $ins = $conn->prepare("INSERT IGNORE INTO `schema_migrations` (`file`) VALUES (?)");
                if ($ins) { $ins->bind_param("s", $base); $ins->execute(); $ins->close(); }
            } catch (\Throwable $e) {}
        }
    }
}
