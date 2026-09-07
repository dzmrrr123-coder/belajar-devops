<?php
namespace App\Http;
class Flash {
    public static function set(string $t, string $m): void { $_SESSION['flash'] = ['type'=>$t,'message'=>$m]; }
    public static function get(): ?array {
        if (!isset($_SESSION['flash'])) return null;
        $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
    }
}
