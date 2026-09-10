<?php
namespace App\Cache;
class Keys {
    public const LEADERBOARD_TTL = 60;
    public const DASHBOARD_TTL = 60;
    public static function leaderboard(string $scope, int $page, int $per): string {
        return "lb:{$scope}:p{$page}:n{$per}";
    }
    public static function leaderboardPrefix(): string { return 'lb:'; }
    public static function dashboard(int $uid): string { return "dash:{$uid}"; }
    public static function hub(int $uid, string $track): string { return "hub:{$uid}:{$track}"; }
    public static function hubPrefix(int $uid): string { return "hub:{$uid}:"; }
    public static function boardRank(string $scope, int $uid): string { return "rank:{$scope}:{$uid}"; }
    public static function racers(int $cid): string { return "racers:{$cid}"; }
    public static function xpWeek(int $uid): string { return "xpweek:{$uid}"; }
    public static function missions(int $uid, string $date): string { return "missions:{$uid}:{$date}"; }
    public static function userPrefix(int $uid): string { return "dash:{$uid}"; }
}
