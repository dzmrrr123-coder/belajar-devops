<?php
namespace App\Domain\Gamification;
class Level {
    public static function calculate(int $xp): int { return (int)(floor(sqrt(max(0, $xp) / 100)) + 1); }
    public static function baseXp(int $level): int { $level = max(1, $level); return ($level - 1) * ($level - 1) * 100; }
    public static function nextXp(int $xp): int { return self::calculate($xp) * self::calculate($xp) * 100; }
    public static function progress(int $xp) {
        $xp = max(0, $xp); $lv = self::calculate($xp);
        $base = self::baseXp($lv); $next = self::nextXp($xp); $range = $next - $base;
        if ($range <= 0) return 100;
        return min(100, max(0, round(($xp - $base) / $range * 100)));
    }
    public static function rank(int $level): string {
        $r = [1=>'Terminal Cadet',2=>'Junior Scripter',3=>'Git Wrangler',4=>'Backend Craftsman',5=>'Docker Apprentice',6=>'Container Captain',7=>'Cloud Pioneer',8=>'DevOps Specialist',9=>'CI/CD Architect',10=>'Site Reliability Engineer',11=>'Cloud Guru',12=>'DevOps Legend'];
        return $r[min(12, max(1, $level))] ?? 'DevOps Grandmaster';
    }
}
