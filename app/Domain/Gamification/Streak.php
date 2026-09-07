<?php
namespace App\Domain\Gamification;
use App\Http\Flash;
class Streak {
    public static function update(\mysqli $conn, int $uid): int {
        $today=date('Y-m-d'); $y=date('Y-m-d',strtotime('-1 day')); $two=date('Y-m-d',strtotime('-2 days'));
        $s=$conn->prepare("SELECT streak,last_active_date,freeze_tokens,best_streak FROM users WHERE id=?");
        if (!$s) return 0;
        $s->bind_param("i",$uid); $s->execute(); $res=$s->get_result()->fetch_assoc(); $s->close();
        if (!$res) return 0;
        $last=$res['last_active_date']; $streak=(int)$res['streak']; $tok=(int)($res['freeze_tokens']??1); $best=(int)($res['best_streak']??0);
        if ($last===$today) {
            if ($streak>$best) { $up=$conn->prepare("UPDATE users SET best_streak=? WHERE id=?"); if($up){$up->bind_param("ii",$streak,$uid);$up->execute();$up->close();} }
            return $streak;
        }
        $used=false;
        if ($last===$y) $streak++;
        elseif ($last===$two && $tok>0) { $streak++; $tok--; $used=true; }
        else $streak=1;
        if (date('W')!==date('W',strtotime($last?:$today))) $tok=min(2,$tok+1);
        $best=max($best,$streak);
        $u=$conn->prepare("UPDATE users SET streak=?,last_active_date=?,freeze_tokens=?,best_streak=? WHERE id=?");
        if ($u) { $u->bind_param("isiii",$streak,$today,$tok,$best,$uid); $u->execute(); $u->close(); }
        if ($used && session_status()===PHP_SESSION_ACTIVE) Flash::set('info','Streak Freeze dipakai! Streak-mu terselamatkan.');
        return $streak;
    }
}
