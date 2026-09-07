<?php
namespace App\Domain\Gamification;
use App\Domain\Social;
class Badges {
    public static function owned(\mysqli $conn, int $uid): array {
        $out=[];
        try { $q=$conn->prepare("SELECT slug,unlocked_at FROM user_badges WHERE user_id=? ORDER BY unlocked_at ASC"); $q->bind_param("i",$uid); $q->execute(); foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $out[$r['slug']]=$r; $q->close(); }
        catch (\Throwable $e) {}
        return $out;
    }
    public static function check(\mysqli $conn, int $uid): array {
        $defs=Social::badgeDefs(); $owned=self::owned($conn,$uid);
        $cat=['first-quest'=>'quest','quest-5'=>'quest','quest-10'=>'quest','quest-all'=>'quest','focus-1'=>'focus','focus-25'=>'focus','note-1'=>'note','note-25'=>'note','streak-7'=>'streak','streak-30'=>'streak','review-10'=>'review','custom-1'=>'custom','duel-1'=>'duel','duel-5'=>'duel','squad-1'=>'squad','season-5'=>'season'];
        $need=[]; foreach($cat as $s=>$c) if(!isset($owned[$s])&&isset($defs[$s])) $need[$c]=true;
        if(empty($need)) return [];
        $c=['quest'=>0,'focus'=>0,'note'=>0,'streak'=>0,'review'=>0,'custom'=>0,'duel'=>0,'squad'=>0,'season'=>0];
        try {
            if(isset($need['quest'])){ $q=$conn->prepare("SELECT COUNT(*) n FROM user_quests WHERE user_id=?"); $q->bind_param("i",$uid); $q->execute(); $c['quest']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }
            if(isset($need['focus'])){ $q=$conn->prepare("SELECT COUNT(*) n FROM pomodoro_sessions WHERE user_id=? AND mode='focus'"); $q->bind_param("i",$uid); $q->execute(); $c['focus']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }
            if(isset($need['note'])){ $q=$conn->prepare("SELECT (SELECT COUNT(*) FROM errors WHERE user_id=?)+(SELECT COUNT(*) FROM questions WHERE user_id=?) n"); $q->bind_param("ii",$uid,$uid); $q->execute(); $c['note']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }
            if(isset($need['streak'])){ $q=$conn->prepare("SELECT streak FROM users WHERE id=?"); $q->bind_param("i",$uid); $q->execute(); $c['streak']=(int)($q->get_result()->fetch_assoc()['streak']??0); $q->close(); }
            if(isset($need['review'])){ try{ $q=$conn->prepare("SELECT COALESCE(SUM(done_count),0) n FROM reviews WHERE user_id=?"); $q->bind_param("i",$uid); $q->execute(); $c['review']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }catch(\Throwable $e){} }
            if(isset($need['custom'])){ $q=$conn->prepare("SELECT COUNT(*) n FROM quests WHERE user_id=? AND is_custom=1"); $q->bind_param("i",$uid); $q->execute(); $c['custom']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }
            if(isset($need['duel'])){ try{ $q=$conn->prepare("SELECT COUNT(*) n FROM duels WHERE winner_id=?"); $q->bind_param("i",$uid); $q->execute(); $c['duel']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }catch(\Throwable $e){} }
            if(isset($need['squad'])){ try{ $q=$conn->prepare("SELECT COUNT(*) n FROM squad_members WHERE user_id=?"); $q->bind_param("i",$uid); $q->execute(); $c['squad']=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); }catch(\Throwable $e){} }
            if(isset($need['season'])){ try{ $c['season']=Season::tiersClaimed($conn,$uid); }catch(\Throwable $e){} }
        } catch(\Throwable $e){ return []; }
        $rules=['first-quest'=>$c['quest']>=1,'quest-5'=>$c['quest']>=5,'quest-10'=>$c['quest']>=10,'quest-all'=>$c['quest']>=14,'focus-1'=>$c['focus']>=1,'focus-25'=>$c['focus']>=25,'note-1'=>$c['note']>=1,'note-25'=>$c['note']>=25,'streak-7'=>$c['streak']>=7,'streak-30'=>$c['streak']>=30,'review-10'=>$c['review']>=10,'custom-1'=>$c['custom']>=1,'duel-1'=>$c['duel']>=1,'duel-5'=>$c['duel']>=5,'squad-1'=>$c['squad']>=1,'season-5'=>$c['season']>=5];
        $new=[];
        foreach($rules as $s=>$ok) if($ok&&!isset($owned[$s])&&isset($defs[$s])){ try{ $ins=$conn->prepare("INSERT IGNORE INTO user_badges (user_id,slug) VALUES (?,?)"); $ins->bind_param("is",$uid,$s); if($ins->execute()&&$ins->affected_rows>0) $new[]=$s; $ins->close(); }catch(\Throwable $e){} }
        return $new;
    }
}
