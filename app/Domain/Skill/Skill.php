<?php
namespace App\Domain\Skill;
class Skill {
    public static function defs(): array {
        return ['Linux'=>['icon'=>'fas fa-terminal','desc'=>'VPS, terminal & jaringan'],'Git'=>['icon'=>'fas fa-code-branch','desc'=>'Version control & GitHub'],'MySQL'=>['icon'=>'fas fa-database','desc'=>'Database & relasi'],'PHP'=>['icon'=>'fas fa-file-code','desc'=>'Native, OOP & security'],'Laravel'=>['icon'=>'fa-brands fa-laravel','desc'=>'Framework & middleware'],'Docker'=>['icon'=>'fa-brands fa-docker','desc'=>'Container & registry'],'AWS'=>['icon'=>'fa-brands fa-aws','desc'=>'Cloud & deploy'],'General'=>['icon'=>'fas fa-layer-group','desc'=>'Lainnya']];
    }
    public static function forWeek(int $w): string {
        $m=[1=>'MySQL',2=>'PHP',3=>'PHP',4=>'PHP',5=>'Laravel',6=>'Laravel',7=>'Docker',8=>'Docker',9=>'AWS',10=>'Linux',11=>'Git',12=>'General'];
        return $m[max(1,min(12,$w))] ?? 'General';
    }
    public static function normalize(string $t): string {
        $t = strtolower(trim($t));
        if ($t==='') return '';
        $a=['MySQL'=>['mysql','database','db','sql','mariadb','relasi'],'PHP'=>['php','oop','solid','composer'],'Laravel'=>['laravel','eloquent','blade','middleware'],'Docker'=>['docker','container','dockerfile','image'],'Linux'=>['linux','ubuntu','bash','terminal','vps','nginx','ssl','domain','server'],'Git'=>['git','github','version'],'AWS'=>['aws','cloud','ec2','deploy']];
        foreach ($a as $s=>$ks) foreach ($ks as $k) if (strpos($t,$k)!==false) return $s;
        foreach (array_keys(self::defs()) as $s) if (strtolower($s)===$t) return $s;
        return 'General';
    }
    public static function reviewSkillFor(string $src, string $title, string $detail): string {
        $t = self::normalize($title.' '.$detail);
        if ($t!=='' && $t!=='General') return $t;
        foreach (array_keys(self::defs()) as $s) if (stripos($src,$s)!==false) return $s;
        return 'General';
    }
}
