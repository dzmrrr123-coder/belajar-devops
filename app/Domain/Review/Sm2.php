<?php
namespace App\Domain\Review;
class Sm2 {
    public static function nextInterval(int $cur): int {
        foreach ([1,3,7,14,30] as $s) if ($cur < $s) return $s;
        return 30;
    }
    public static function gradeToInt($g): int {
        $map = ['again'=>0,'hard'=>3,'good'=>4,'easy'=>5,'forgot'=>0,'know'=>4];
        if (is_int($g)) return min(5, max(0, $g));
        return $map[strtolower(trim((string)$g))] ?? 4;
    }
    public static function next(float $ease, int $reps, int $interval, $grade): array {
        $ease = max(1.3, min(3.0, $ease ?: 2.5)); $reps = max(0,$reps); $interval = max(1,$interval);
        $g = self::gradeToInt($grade);
        if ($g < 3) return ['interval'=>1,'ease'=>max(1.3,$ease-0.2),'reps'=>0];
        $ne = max(1.3, min(3.0, $ease + (0.1 - (5-$g)*(0.08+(5-$g)*0.02))));
        $next = $reps===0?1:($reps===1?6:(int)round($interval*$ne));
        if ($g===3) $next = max(1,(int)round($next*0.8));
        if ($g===5) $next = (int)round($next*1.15)+1;
        return ['interval'=>min(90,max(1,$next)),'ease'=>round($ne,2),'reps'=>$reps+1];
    }
    public static function labels(): array {
        return ['again'=>['label'=>'Lagi','hint'=>'besok','key'=>'1'],'hard'=>['label'=>'Sulit','hint'=>'segera','key'=>'2'],'good'=>['label'=>'Bisa','hint'=>'sesuai jadwal','key'=>'3'],'easy'=>['label'=>'Mudah','hint'=>'lama','key'=>'4']];
    }
}
