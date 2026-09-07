<?php
namespace App\Domain;
class Sponsor {
    public static function ensureTables(\mysqli $conn): void {
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `sponsors` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(80) NOT NULL UNIQUE, `url` VARCHAR(255) NULL, `active` TINYINT NOT NULL DEFAULT 1, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
    }
    public static function all(\mysqli $conn): array {
        self::ensureTables($conn);
        try {
            $r = $conn->query("SELECT s.name, s.url, (SELECT COUNT(*) FROM incident_challenges c WHERE c.slug LIKE CONCAT('%', LOWER(REPLACE(s.name,' ', '-')), '%')) AS labs FROM sponsors s WHERE s.active = 1 ORDER BY s.name ASC");
            if ($r) { $o = $r->fetch_all(MYSQLI_ASSOC); $r->free(); return $o; }
        } catch (\Throwable $e) {}
        return [];
    }
    public static function seed(\mysqli $conn): void {
        self::ensureTables($conn);
        try {
            @$conn->query("INSERT IGNORE INTO sponsors (name, url) VALUES ('Lab Networking SMK', NULL), ('Studio Portofolio', NULL)");
        } catch (\Throwable $e) {}
    }
    public static function clean(string $s): string {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $s)), 0, 80);
    }
}
