<?php
// Smoke cache + rate-limit tanpa DB. Jalankan: php tests/cache_smoke.php
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

$fail = 0;
function smoke_check($label, $cond) {
    global $fail;
    if (!$cond) { $fail++; fwrite(STDERR, "FAIL {$label}\n"); }
}

$k = 'smoke_' . bin2hex(random_bytes(4));
$v = \App\Cache\Store::remember($k, 60, fn() => ['a' => 1]);
smoke_check('cache write', ($v['a'] ?? null) === 1);
$v2 = \App\Cache\Store::remember($k, 60, fn() => ['a' => 2]);
smoke_check('cache hit', ($v2['a'] ?? null) === 1);
\App\Cache\Store::forget($k);
$v3 = \App\Cache\Store::remember($k, 60, fn() => ['a' => 3]);
smoke_check('cache forget', ($v3['a'] ?? null) === 3);
\App\Cache\Store::forget($k);

$rk = 'smoke_rl_' . bin2hex(random_bytes(4));
smoke_check('rl 1', \App\Http\RateLimit::hit($rk, 2, 60) === false);
smoke_check('rl 2', \App\Http\RateLimit::hit($rk, 2, 60) === false);
smoke_check('rl 3 blocked', \App\Http\RateLimit::hit($rk, 2, 60) === true);

echo $fail === 0 ? "cache smoke OK\n" : "cache smoke FAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
