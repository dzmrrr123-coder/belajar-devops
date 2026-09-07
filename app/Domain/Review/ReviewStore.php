<?php
namespace App\Domain\Review;
use App\Domain\Skill\Skill;
class ReviewStore {
    public static function delete(\mysqli $conn, int $uid, string $src, int $sid): void {
        try { $s=$conn->prepare("DELETE FROM reviews WHERE user_id=? AND source=? AND source_id=?"); $s->bind_param("isi",$uid,$src,$sid); $s->execute(); $s->close(); }
        catch(\Throwable $e){}
    }
    public static function schedule(\mysqli $conn, int $uid, string $src, int $sid, string $title, string $detail='', string $skill=''): void {
        try {
            $title=mb_substr(trim($title)?:'Review',0,255); $detail=mb_substr($detail,0,2000);
            $skill=mb_substr(trim($skill)?:Skill::reviewSkillFor($src,$title,$detail),0,32);
            $s=$conn->prepare("INSERT INTO reviews (user_id,source,source_id,title,detail,next_due,interval_day,skill) VALUES (?,?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 1 DAY),1,?) ON DUPLICATE KEY UPDATE title=VALUES(title),detail=VALUES(detail),skill=VALUES(skill)");
            if(!$s){ $fb=$conn->prepare("INSERT INTO reviews (user_id,source,source_id,title,detail,next_due,interval_day) VALUES (?,?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 1 DAY),1) ON DUPLICATE KEY UPDATE title=VALUES(title),detail=VALUES(detail)"); if(!$fb) return; $fb->bind_param("isiss",$uid,$src,$sid,$title,$detail); $fb->execute(); $fb->close(); return; }
            $s->bind_param("isssss",$uid,$src,$sid,$title,$detail,$skill); $s->execute(); $s->close();
        } catch(\Throwable $e){}
    }
}
