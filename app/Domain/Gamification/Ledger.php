<?php
namespace App\Domain\Gamification;
class Ledger {
    public static function hasRef(\mysqli $conn): bool {
        static $c = null;
        if ($c !== null) return $c;
        try { $chk=$conn->query("SHOW COLUMNS FROM `xp_events` LIKE 'ref_type'"); $c=($chk&&$chk->num_rows>0); if($chk)$chk->free(); }
        catch (\Throwable $e) { $c=false; }
        return $c;
    }
    public static function award(\mysqli $conn, int $uid, int $amt, string $reason='other', $refType=null, $refId=null): void {
        if ($amt===0) return;
        $s=$conn->prepare("UPDATE users SET xp=GREATEST(0,xp+?) WHERE id=?");
        $s->bind_param("ii",$amt,$uid); $s->execute(); $s->close();
        try {
            if (self::hasRef($conn)) { $l=$conn->prepare("INSERT INTO xp_events (user_id,amount,reason,ref_type,ref_id) VALUES (?,?,?,?,?)"); $l->bind_param("iissi",$uid,$amt,$reason,$refType,$refId); $l->execute(); $l->close(); }
            else { $l=$conn->prepare("INSERT INTO xp_events (user_id,amount,reason) VALUES (?,?,?)"); $l->bind_param("iis",$uid,$amt,$reason); $l->execute(); $l->close(); }
        } catch (\Throwable $e) {}
        \App\Cache\Invalidator::userChanged($uid);
    }
    public static function sum(\mysqli $conn, int $uid): int {
        try { $q=$conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id=?"); $q->bind_param("i",$uid); $q->execute(); $n=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); return max(0,$n); }
        catch (\Throwable $e) { return 0; }
    }
    public static function sync(\mysqli $conn, int $uid): int {
        try {
            $sum=self::sum($conn,$uid);
            $q=$conn->prepare("SELECT xp FROM users WHERE id=?"); $q->bind_param("i",$uid); $q->execute();
            $cur=(int)($q->get_result()->fetch_assoc()['xp']??0); $q->close();
            if ($sum<$cur) { $b=$conn->prepare("INSERT INTO xp_events (user_id,amount,reason) VALUES (?,?,'backfill')"); $b->bind_param("ii",$uid,$diff=$cur-$sum); $b->execute(); $b->close(); return $cur; }
            $up=$conn->prepare("UPDATE users SET xp=? WHERE id=?"); $up->bind_param("ii",$sum,$uid); $up->execute(); $up->close();
            \App\Cache\Invalidator::userChanged($uid);
            return $sum;
        } catch (\Throwable $e) { return 0; }
    }
    public static function awardedFor(\mysqli $conn, int $uid, string $t, int $id): int {
        try { $q=$conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id=? AND ref_type=? AND ref_id=? AND amount>0"); $q->bind_param("isi",$uid,$t,$id); $q->execute(); $n=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); return $n; }
        catch (\Throwable $e) { return 0; }
    }
    public static function weekly(\mysqli $conn, int $uid): int {
        try { $q=$conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"); $q->bind_param("i",$uid); $q->execute(); $n=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); return max(0,$n); }
        catch (\Throwable $e) { return 0; }
    }
    public static function dailyReason(\mysqli $conn, int $uid, string $r): int {
        try { $q=$conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id=? AND reason=? AND amount>0 AND created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY"); $q->bind_param("is",$uid,$r); $q->execute(); $n=(int)($q->get_result()->fetch_assoc()['n']??0); $q->close(); return max(0,$n); }
        catch (\Throwable $e) { return 0; }
    }
}
