<?php
namespace App\Cache;
class Invalidator {
    public static function userChanged(int $uid): void {
        try {
            Store::forget(Keys::dashboard($uid));
            Store::forget(Keys::xpWeek($uid));
            Store::forget(Keys::missions($uid, date('Y-m-d')));
            Store::forget('pulse:' . $uid);
            Store::forgetPrefix(Keys::leaderboardPrefix());
        } catch (\Throwable $e) {}
    }
}
