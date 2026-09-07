<?php
namespace App\Domain\Auth;
use App\Domain\Gamification\Streak;
use App\Http\Flash;
class Remember {
    public const COOKIE='lt_remember'; public const DAYS=30;
    public static function opts(int $exp): array {
        return ['expires'=>$exp,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax'];
    }
    public static function create(\mysqli $conn, int $uid): void {
        try {
            @$conn->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");
            $sel=bin2hex(random_bytes(12)); $val=bin2hex(random_bytes(32)); $hash=hash('sha256',$val);
            $exp=date('Y-m-d H:i:s',time()+self::DAYS*86400);
            $s=$conn->prepare("INSERT INTO remember_tokens (user_id,selector,validator_hash,expires_at) VALUES (?,?,?,?)");
            if(!$s) return; $s->bind_param("isss",$uid,$sel,$hash,$exp);
            if($s->execute()) setcookie(self::COOKIE,$sel.':'.$val,self::opts(time()+self::DAYS*86400));
            $s->close();
        } catch(\Throwable $e){ error_log("remember create: ".$e->getMessage()); }
    }
    public static function clear(?\mysqli $conn=null): void {
        $c=$_COOKIE[self::COOKIE]??''; $p=explode(':',$c,2);
        if(count($p)===2&&$conn){ try{ $s=$conn->prepare("DELETE FROM remember_tokens WHERE selector=?"); if($s){$s->bind_param("s",$p[0]);$s->execute();$s->close();} }catch(\Throwable $e){} }
        setcookie(self::COOKIE,'',self::opts(time()-3600)); unset($_COOKIE[self::COOKIE]);
    }
    public static function touch(\mysqli $conn, int $uid): void {
        try{ $s=$conn->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?"); if($s){$s->bind_param("i",$uid);$s->execute();$s->close();} }catch(\Throwable $e){}
    }
}
