<?php
namespace App\Domain\Gamification;
class Xp {
    public const NOTE_DAILY_CAP = 25;
    public static function apply(int $base, float $mult): int { return (int)ceil($base * $mult); }
    public static function capped(int $wanted, int $todaySum, int $cap): int {
        $left = max(0, $cap - $todaySum);
        return $left <= 0 ? 0 : min(max(0, $wanted), $left);
    }
}
