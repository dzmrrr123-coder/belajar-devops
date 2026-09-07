<?php
namespace App\Domain\Skill;
class Skill {
    public static function defs(?string $track = null): array {
        $all=['Linux'=>['icon'=>'fas fa-terminal','desc'=>'VPS, terminal & jaringan'],'Git'=>['icon'=>'fas fa-code-branch','desc'=>'Version control & GitHub'],'MySQL'=>['icon'=>'fas fa-database','desc'=>'Database & relasi'],'PHP'=>['icon'=>'fas fa-file-code','desc'=>'Native, OOP & security'],'Laravel'=>['icon'=>'fa-brands fa-laravel','desc'=>'Framework & middleware'],'Docker'=>['icon'=>'fa-brands fa-docker','desc'=>'Container & registry'],'AWS'=>['icon'=>'fa-brands fa-aws','desc'=>'Cloud & deploy'],'Networking'=>['icon'=>'fas fa-network-wired','desc'=>'IP, subnet, DNS & routing'],'Testing'=>['icon'=>'fas fa-vial','desc'=>'Software testing & QA'],'Desain'=>['icon'=>'fas fa-palette','desc'=>'Dasar visual & komposisi'],'Tipografi'=>['icon'=>'fas fa-font','desc'=>'Huruf & keterbacaan'],'Branding'=>['icon'=>'fas fa-award','desc'=>'Logo & identitas merek'],'UI/UX'=>['icon'=>'fas fa-object-group','desc'=>'Wireframe & pengalaman pakai'],'Ilustrasi'=>['icon'=>'fas fa-paint-brush','desc'=>'Vektor & gambar'],'Motion'=>['icon'=>'fas fa-film','desc'=>'Animasi & micro-interaction'],'General'=>['icon'=>'fas fa-layer-group','desc'=>'Lainnya']];
        if ($track === null) return $all;
        $keep=\App\Domain\Track\Tracks::skills($track);
        return array_intersect_key($all, array_flip($keep));
    }
    public static function forWeek(int $w, string $track = 'devops'): string {
        $m=\App\Domain\Track\Tracks::weeks($track);
        return $m[max(1,min(12,$w))] ?? 'General';
    }
    public static function normalize(string $t): string {
        $t = strtolower(trim($t));
        if ($t==='') return '';
        $a=['Testing'=>['testing','phpunit','pest','assertion','selenium','unit test','integration test'],'Networking'=>['networking','subnet','cidr','ip address','dns','tcp','udp','ping','routing','vlan'],'MySQL'=>['mysql','database','db','sql','mariadb','relasi'],'PHP'=>['php','oop','solid','composer'],'Laravel'=>['laravel','eloquent','blade','middleware'],'Docker'=>['docker','container','dockerfile','image'],'Linux'=>['linux','ubuntu','bash','terminal','vps','nginx','ssl','domain','server'],'Git'=>['git','github','version'],'AWS'=>['aws','cloud','ec2','deploy'],'Desain'=>['desain','design grafis','komposisi','warna','layout'],'Tipografi'=>['tipografi','typography','font','typeface'],'Branding'=>['branding','brand identity','logo'],'UI/UX'=>['ui/ux','wireframe','figma','prototipe','usability','user experience'],'Ilustrasi'=>['ilustrasi','illustration','vektor'],'Motion'=>['motion design','animasi','micro-interaction']];
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
