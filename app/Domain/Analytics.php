<?php
namespace App\Domain;
class Analytics {
    public static function trend(int $now, int $prev): int {
        $now=max(0,$now); $prev=max(0,$prev);
        if ($now===0 && $prev===0) return 0;
        if ($prev<=0) return 100;
        return (int)round(($now-$prev)/$prev*100);
    }
    public static function consistency(int $a, int $t): int {
        $t=max(1,$t); $a=min($t,max(0,$a));
        return (int)round($a/$t*100);
    }
    public static function heat(int $xp): int {
        $xp=max(0,$xp);
        if ($xp<=0) return 0; if ($xp<10) return 1; if ($xp<25) return 2; if ($xp<50) return 3; return 4;
    }
    public static function verdict(int $s): string {
        if ($s>=70) return 'Ritme kuat. Tinggal jaga.';
        if ($s>=40) return 'Ritme tumbuh. Tambah 1 sesi kecil.';
        if ($s>0) return 'Ritme rapuh. Satu aksi hari ini cukup.';
        return 'Belum mulai. Satu sesi 25 menit memecah kebekuan.';
    }
    public static function daysLeft(int $done, int $total, float $avg): int {
        $left=max(0,$total-max(0,$done));
        if ($left<=0) return 0; if ($avg<=0) return -1;
        return (int)ceil($left/$avg);
    }
    public static function predictLabel(int $d): string {
        if ($d<0) return 'Selesaikan 1 quest untuk memproyeksi target.';
        if ($d===0) return 'Roadmap tuntas. Pertahankan dengan review.';
        if ($d===1) return 'Tuntas besok jika ritme dijaga.';
        if ($d<=14) return "Tuntas sekitar {$d} hari lagi.";
        if ($d<=60) return 'Sekitar '.(int)ceil($d/7).' minggu menuju tuntas.';
        return 'Sekitar '.(int)ceil($d/30).' bulan menuju tuntas di ritme ini.';
    }
    public static function weekLabel(int $o): string {
        if ($o===0) return 'Minggu ini'; if ($o===1) return 'Lalu'; return 'M-'.$o;
    }
}
