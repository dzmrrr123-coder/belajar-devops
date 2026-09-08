<?php
namespace App\Domain\Gamification;
class Combo {
    public static function tier(int $done, int $total = 3): float {
        if ($done >= $total) return 2.0;
        if ($done === $total - 1) return 1.5;
        return 1.0;
    }
    public static function countDone(array $missions): int {
        $n = 0;
        foreach ($missions as $m) if (!empty($m['done'])) $n++;
        return $n;
    }
    public static function multiplier(\mysqli $conn, int $uid): float {
        try { $m = Missions::status($conn, $uid); return self::tier(self::countDone($m), count($m) ?: 3); }
        catch (\Throwable $e) { return 1.0; }
    }
    public static function label(float $mult): string {
        return 'x' . rtrim(rtrim(number_format($mult, 1, '.', ''), '0'), '.');
    }
    public static function nextHint(int $done, int $total = 3): string {
        if ($done >= $total) return 'Kombo maks!';
        if ($done === $total - 1) return '1 aksi lagi → x2';
        if ($done === $total - 2) return '1 aksi lagi → x1,5';
        return ($total - $done - 1) . ' aksi lagi → x1,5';
    }
}
