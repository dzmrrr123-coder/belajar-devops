<?php
namespace App\Domain;
class Shop {
    public static function rerollWin(int $roll, int $draw): int {
        $roll=max(1,min(100,$roll)); $draw=(int)$draw;
        if ($roll<=60) return max(5,min(10,$draw));
        if ($roll<=90) return max(11,min(20,$draw));
        return max(21,min(30,$draw));
    }
    public static function rerollEv(): float { return 0.6*7.5+0.3*15.5+0.1*25.5; }
}
