<?php
namespace App\Domain;
use App\Domain\Skill\Skill;
class Onboarding {
    public static function targets(string $track = 'devops'): array { return \App\Domain\Track\Tracks::targets($track); }
    public static function minutes(): array { return [15,25,45,60]; }
    public static function plan(string $target, int $minutes, array $skills, string $track = 'devops'): array {
        $track = \App\Domain\Track\Tracks::normalize($track);
        $target = in_array($target, self::targets($track), true) ? $target : 'Masih ragu';
        $minutes = in_array($minutes, self::minutes(), true) ? $minutes : 25;
        $defs = Skill::defs($track); $clean = [];
        foreach ($skills as $s) { $s=trim((string)$s); if (isset($defs[$s]) && !in_array($s,$clean,true)) $clean[]=$s; }
        $clean = array_slice($clean, 0, 3);
        $out = [['title'=>"Siapkan senjata {$target}",'description'=>"Install dan cek tools utama ({$minutes} menit): terminal, git, dan editor.",'week'=>1,'xp_reward'=>10]];
        foreach ($clean as $sk) $out[] = ['title'=>"Fondasi {$sk} {$minutes} menit",'description'=>"Pelajari dasar {$sk} selama {$minutes} menit untuk target {$target}.",'week'=>1,'xp_reward'=>10];
        return $out;
    }
}
