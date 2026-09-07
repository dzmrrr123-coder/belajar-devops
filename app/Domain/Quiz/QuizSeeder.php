<?php
namespace App\Domain\Quiz;
class QuizSeeder {
    public static function seed(\mysqli $conn, int $uid): int {
        $n=0;
        try {
            try{ @$conn->query("ALTER TABLE `quiz_cards` ADD COLUMN `topic` VARCHAR(32) NOT NULL DEFAULT 'General'"); }catch(\Throwable $e){}
            $ins=$conn->prepare("INSERT INTO quiz_cards (user_id,source,source_id,question,answer,topic) VALUES (?,'bank',?,?,?,?) ON DUPLICATE KEY UPDATE question=VALUES(question),answer=VALUES(answer),topic=VALUES(topic)");
            if(!$ins) return 0;
            foreach(QuizBank::cards() as $i=>$c){ $sid=$i+1; $t=mb_substr(trim((string)($c[0]??'General'))?:'General',0,32); $q=mb_substr(trim((string)$c[1]),0,255); $a=mb_substr(trim((string)$c[2]),0,2000); if($q===''||$a==='') continue; $ins->bind_param("iisss",$uid,$sid,$q,$a,$t); if($ins->execute()&&$ins->affected_rows>0) $n++; }
            $ins->close();
        } catch(\Throwable $e){}
        return $n;
    }
}
