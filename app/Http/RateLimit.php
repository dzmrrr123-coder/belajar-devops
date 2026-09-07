<?php
namespace App\Http;
use App\Cache\Store;
class RateLimit {
    public static function hit(string $key, int $max, int $window): bool {
        $max = max(1, $max); $window = max(1, $window);
        $sid = session_id() ?: 'cli';
        $ck = 'rl:' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sid) . ':' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $now = time();
        try {
            $got = Store::get($ck);
            if (!$got['hit'] || !is_array($got['value']) || ($now - (int)($got['value']['t'] ?? 0)) >= $window) {
                Store::set($ck, ['t' => $now, 'c' => 1], $window);
                return false;
            }
            $e = $got['value'];
            $e['c'] = (int)($e['c'] ?? 0) + 1;
            Store::set($ck, $e, $window);
            return $e['c'] > $max;
        } catch (\Throwable $e2) {
            return false;
        }
    }
}
