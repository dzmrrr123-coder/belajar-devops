<?php
namespace App\Domain;
class Social {
    public static function cheerClean(string $b): string {
        $t = trim(preg_replace('/\s+/', ' ', $b));
        return mb_strlen($t) < 2 ? '' : mb_substr($t, 0, 140);
    }
    public static function badgeShare(string $u, string $b): string { return trim($u).' meraih badge "'.trim($b).'" di Learn Tracker DevOps'; }
    public static function avatarFrames(): array {
        return ['default'=>['name'=>'Polos','hint'=>'Untuk semua orang'],'ring'=>['name'=>'Cincin','hint'=>'Capai Level 3'],'ember'=>['name'=>'Bara','hint'=>'Streak terbaik 7 hari'],'gold'=>['name'=>'Emas','hint'=>'Capai Level 5'],'legend'=>['name'=>'Legenda','hint'=>'Badge Roadmap Tuntas / Level 8']];
    }
    public static function avatarUnlocked(string $f, int $lv, int $best, array $badges, bool $owner=false): bool {
        if ($owner || $f==='default') return true;
        if ($f==='ring') return $lv>=3; if ($f==='ember') return $best>=7;
        if ($f==='gold') return $lv>=5;
        if ($f==='legend') return $lv>=8 || isset($badges['quest-all']);
        return false;
    }
    public static function badgeDefs(): array {
        return ['first-quest'=>['name'=>'Langkah Pertama','desc'=>'Selesaikan 1 quest','icon'=>'fa-flag'],'quest-5'=>['name'=>'Quest Hunter 5','desc'=>'Selesaikan 5 quest','icon'=>'fa-map'],'quest-10'=>['name'=>'Quest Hunter 10','desc'=>'Selesaikan 10 quest','icon'=>'fa-map-location-dot'],'quest-all'=>['name'=>'Roadmap Tuntas','desc'=>'Selesaikan 14 quest','icon'=>'fa-crown'],'focus-1'=>['name'=>'Fokus Perdana','desc'=>'1 sesi fokus','icon'=>'fa-clock'],'focus-25'=>['name'=>'Deep Worker','desc'=>'25 sesi fokus','icon'=>'fa-brain'],'note-1'=>['name'=>'Bug Reporter','desc'=>'Tulis 1 catatan','icon'=>'fa-note-sticky'],'note-25'=>['name'=>'Bug Hunter','desc'=>'25 catatan error/tanya','icon'=>'fa-bug'],'streak-7'=>['name'=>'Konsisten 7 Hari','desc'=>'Streak 7 hari','icon'=>'fa-fire'],'streak-30'=>['name'=>'Unstoppable 30','desc'=>'Streak 30 hari','icon'=>'fa-volcano'],'review-10'=>['name'=>'Reviewer','desc'=>'10 review Tahu','icon'=>'fa-rotate-right'],'custom-1'=>['name'=>'Inisiatif','desc'=>'Buat 1 quest custom','icon'=>'fa-plus'],'duel-1'=>['name'=>'Duel Pertama Menang','desc'=>'Menangkan 1 duel 1v1','icon'=>'fa-hand-fist'],'duel-5'=>['name'=>'Duelist','desc'=>'Menangkan 5 duel 1v1','icon'=>'fa-trophy'],'squad-1'=>['name'=>'Squad Up','desc'=>'Gabung squad pertama','icon'=>'fa-users'],'season-5'=>['name'=>'Season Tuntas','desc'=>'Klaim 5 tier dalam 1 season','icon'=>'fa-crown']];
    }
}
