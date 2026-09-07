<?php
namespace App\Domain\Tkj;
class Topo {
    public static function parse(string $cidr): array {
        $cidr = trim($cidr);
        if (!preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/', $cidr, $m)) return ['ok' => false, 'msg' => 'Format: 192.168.1.0/24'];
        $oct = [(int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]];
        $p = (int)$m[5];
        foreach ($oct as $o) if ($o < 0 || $o > 255) return ['ok' => false, 'msg' => 'Oktet 0-255.'];
        if ($p < 8 || $p > 30) return ['ok' => false, 'msg' => 'Prefix 8-30.'];
        $ip = ($oct[0] << 24) | ($oct[1] << 16) | ($oct[2] << 8) | $oct[3];
        $mask = $p === 0 ? 0 : (~0 << (32 - $p)) & 0xFFFFFFFF;
        $net = $ip & $mask;
        $bcast = $net | (~$mask & 0xFFFFFFFF);
        $hosts = $p >= 31 ? 0 : (int)(pow(2, 32 - $p) - 2);
        return ['ok' => true, 'network' => long2ip($net), 'broadcast' => long2ip($bcast), 'mask' => long2ip($mask), 'hosts' => $hosts, 'prefix' => $p, 'class' => self::klass($oct[0]), 'private' => self::isPrivate($oct)];
    }
    public static function klass(int $first): string {
        if ($first < 128) return 'A';
        if ($first < 192) return 'B';
        return 'C';
    }
    public static function isPrivate(array $o): bool {
        if ($o[0] === 10) return true;
        if ($o[0] === 172 && $o[1] >= 16 && $o[1] <= 31) return true;
        if ($o[0] === 192 && $o[1] === 168) return true;
        return false;
    }
    public static function ensureTables(\mysqli $conn): void {
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `topo_saves` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `name` VARCHAR(80) NOT NULL DEFAULT 'topologi', `payload` TEXT NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
    }
}
