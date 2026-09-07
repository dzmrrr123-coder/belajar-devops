<?php
namespace App\Domain\Gamification;
class Missions {
    public static function status(\mysqli $conn, int $uid): array {
        $defs=Mission::defs(); $out=[];
        foreach($defs as $k=>$d) $out[$k]=['done'=>false,'claimed'=>false]+$d;
        try {
            $q=$conn->prepare("SELECT (SELECT COUNT(*) FROM user_quests WHERE user_id=? AND completed_at=CURDATE()) qc,(SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id=? AND completed_at>=CURDATE() AND completed_at<CURDATE()+INTERVAL 1 DAY) fc,(SELECT COUNT(*) FROM errors WHERE user_id=? AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY)+(SELECT COUNT(*) FROM questions WHERE user_id=? AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY) nc,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='quest1' AND claimed_at IS NOT NULL) c1,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='focus1' AND claimed_at IS NOT NULL) c2,(SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='note1' AND claimed_at IS NOT NULL) c3");
            $q->bind_param("iiiiiii",$uid,$uid,$uid,$uid,$uid,$uid,$uid); $q->execute();
            $mc=$q->get_result()->fetch_assoc()?:[]; $q->close();
            $out['quest1']['done']=((int)($mc['qc']??0))>0; $out['focus1']['done']=((int)($mc['fc']??0))>0; $out['note1']['done']=((int)($mc['nc']??0))>0;
            if(((int)($mc['c1']??0))>0)$out['quest1']['claimed']=true;
            if(((int)($mc['c2']??0))>0)$out['focus1']['claimed']=true;
            if(((int)($mc['c3']??0))>0)$out['note1']['claimed']=true;
        } catch(\Throwable $e){}
        return $out;
    }
    public static function multiplier(\mysqli $conn, int $uid): float {
        try { foreach(self::status($conn,$uid) as $m) if(empty($m['done'])) return 1.0; return 1.5; }
        catch(\Throwable $e){ return 1.0; }
    }
}
