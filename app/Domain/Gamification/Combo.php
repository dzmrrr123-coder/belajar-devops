<?php
namespace App\Domain\Gamification;
class Combo {
    public static function tier(int $done): float {
        if ($done >= 3) return 2.0;
        if ($done === 2) return 1.5;
        return 1.0;
    }
    public static function countDone(array $missions): int {
        $n = 0;
        foreach ($missions as $m) if (!empty($m['done'])) $n++;
        return $n;
    }
    public static function multiplier(\mysqli $conn, int $uid): float {
        try { return self::tier(self::countDone(Missions::status($conn, $uid))); }
        catch (\Throwable $e) { return 1.0; }
    }
    public static function label(float $mult): string {
        return 'x' . rtrim(rtrim(number_format($mult, 1, '.', ''), '0'), '.');
    }
    public static function nextHint(int $done): string {
        if ($done >= 3) return 'Combo maks!';
        if ($done === 2) return '1 aksi lagi → x2';
        if ($done === 1) return '1 aksi lagi → x1.5';
        return '2 aksi hari ini → x1.5';
    }
}
