<?php
// Smoke rate-limit: verifikasi perilaku limit + isolasi kunci (identifier / per-sesi).
// Jalankan: php tests/ratelimit_smoke.php
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

$fail = 0;
function rl_check($label, $cond) {
    global $fail;
    if (!$cond) { $fail++; fwrite(STDERR, "FAIL {$label}\n"); }
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

// 1) Batas dasar per-identifier: max 2, hit ke-3 diblokir.
$k = 'rlt_' . bin2hex(random_bytes(4));
rl_check('id hit1 false', \App\Http\RateLimit::hit($k, 2, 60, 'alice') === false);
rl_check('id hit2 false', \App\Http\RateLimit::hit($k, 2, 60, 'alice') === false);
rl_check('id hit3 true',  \App\Http\RateLimit::hit($k, 2, 60, 'alice') === true);

// 2) Identifier berbeda = kunci berbeda (tidak saling memblokir).
rl_check('bob terpisah false', \App\Http\RateLimit::hit($k, 2, 60, 'bob') === false);

// 3) Key berbeda = kunci berbeda.
rl_check('key lain false', \App\Http\RateLimit::hit($k . '_x', 2, 60, 'alice') === false);

// 4) Per-sesi (identifier null): max 1 → hit ke-2 diblokir, dan tidak bentrok dgn per-IP.
$k2 = 'rlt_' . bin2hex(random_bytes(4));
rl_check('sesi hit1 false', \App\Http\RateLimit::hit($k2, 1, 60) === false);
rl_check('sesi hit2 true',  \App\Http\RateLimit::hit($k2, 1, 60) === true);

// 5) X-Forwarded-For dari IP TIDAK tepercaya harus DIABAIKAN (anti-spoof).
$k3 = 'rlt_' . bin2hex(random_bytes(4));
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
$r1 = \App\Http\RateLimit::hit($k3, 1, 60, 'x');
$r2 = \App\Http\RateLimit::hit($k3, 1, 60, 'x');
rl_check('xff diabaikan (hit1 false)', $r1 === false);
rl_check('xff diabaikan (hit2 true)',  $r2 === true);

echo $fail === 0 ? "ratelimit smoke OK\n" : "ratelimit smoke FAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
