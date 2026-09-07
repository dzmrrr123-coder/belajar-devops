<?php
namespace App\Domain;
class ChallengeStore {
    public static function ensureWeekly(\mysqli $conn): ?array {
        $key=Challenge::weekKey(); $def=Challenge::forWeek($key);
        try {
            $ins=$conn->prepare("INSERT IGNORE INTO challenges (week_key,title,target_xp) VALUES (?,?,?)"); $ins->bind_param("ssi",$key,$def['title'],$def['target_xp']); $ins->execute(); $ins->close();
            $s=$conn->prepare("SELECT id,week_key,title,target_xp FROM challenges WHERE week_key=?"); $s->bind_param("s",$key); $s->execute(); $row=$s->get_result()->fetch_assoc(); $s->close();
            return $row?:null;
        } catch(\Throwable $e){ return null; }
    }
}
