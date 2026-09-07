<?php
namespace App\Domain;
class Challenge {
    public static function weekKey($ts=null): string { return date('o-\\WW', $ts ?? time()); }
    public static function forWeek(string $k): array {
        $n=0; if (preg_match('/W(\d{1,2})$/',$k,$m)) $n=(int)$m[1];
        $t=[['Sprint 80 XP',80],['Sprint 100 XP',100],['Sprint 120 XP',120],['Sprint 150 XP',150]];
        $p=$t[$n%4]; return ['title'=>'Tantangan minggu ini: '.$p[0],'target_xp'=>$p[1]];
    }
    public static function pct(int $xp, int $t): int { $t=max(1,$t); return min(100,(int)round(max(0,$xp)/$t*100)); }
}
