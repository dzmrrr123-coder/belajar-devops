<?php
namespace App\Domain\Gamification;
class Mission {
    public static function defs(): array {
        return ['quest1'=>['label'=>'Selesaikan 1 quest','xp'=>5,'icon'=>'fa-map'],'focus1'=>['label'=>'1 sesi fokus','xp'=>5,'icon'=>'fa-clock'],'note1'=>['label'=>'Tulis 1 catatan','xp'=>5,'icon'=>'fa-note-sticky']];
    }
}
