<?php
namespace App\Domain\Gamification;
class Missions {
    public static function bonusForDate(?int $ts = null): array {
        $day = (int)date('j', $ts ?? time());
        if ($day % 2 === 0) {
            return ['key' => 'bonus_full', 'label' => 'Tuntaskan 3 misi', 'xp' => 10, 'icon' => 'fa-star'];
        }
        return ['key' => 'bonus_focus2', 'label' => '2 sesi fokus', 'xp' => 10, 'icon' => 'fa-bolt'];
    }
    public static function status(\mysqli $conn, int $uid): array {
        $defs=Mission::defs(); $out=[];
        foreach($defs as $k=>$d) $out[$k]=['done'=>false,'claimed'=>false]+$d;
        $bonus=self::bonusForDate();
        $out[$bonus['key']]=['done'=>false,'claimed'=>false]+$bonus;
        try {
            $q=$conn->prepare("SELECT (SELECT COUNT(*) FROM user_quests WHERE user_id=? AND completed_at=CURDATE()) qc,(SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id=? AND completed_at>=CURDATE() AND completed_at<CURDATE()+INTERVAL 1 DAY) fc,(SELECT COUNT(*) FROM errors WHERE user_id=? AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY)+(SELECT COUNT(*) FROM questions WHERE user_id=? AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY) nc,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='quest1' AND claimed_at IS NOT NULL) c1,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='focus1' AND claimed_at IS NOT NULL) c2,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='note1' AND claimed_at IS NOT NULL) c3,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key=? AND claimed_at IS NOT NULL) c4");
            $bk=$bonus['key'];
            $q->bind_param("iiiiiiiis",$uid,$uid,$uid,$uid,$uid,$uid,$uid,$uid,$bk); $q->execute();
            $mc=$q->get_result()->fetch_assoc()?:[]; $q->close();
            $qd=((int)($mc['qc']??0))>0; $fd=((int)($mc['fc']??0))>0; $nd=((int)($mc['nc']??0))>0;
            $out['quest1']['done']=$qd; $out['focus1']['done']=$fd; $out['note1']['done']=$nd;
            $out[$bonus['key']]['done'] = $bk==='bonus_full' ? ($qd&&$fd&&$nd) : (((int)($mc['fc']??0))>=2);
            if(((int)($mc['c1']??0))>0)$out['quest1']['claimed']=true;
            if(((int)($mc['c2']??0))>0)$out['focus1']['claimed']=true;
            if(((int)($mc['c3']??0))>0)$out['note1']['claimed']=true;
            if(((int)($mc['c4']??0))>0)$out[$bonus['key']]['claimed']=true;
        } catch(\Throwable $e){}
        return $out;
    }
    public static function multiplier(\mysqli $conn, int $uid): float {
        try { foreach(self::status($conn,$uid) as $m) if(empty($m['done'])) return 1.0; return 1.5; }
        catch(\Throwable $e){ return 1.0; }
    }
}
