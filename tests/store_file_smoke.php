<?php
// Smoke Store: pastikan key ber-':' (umum dipakai Keys::*) benar-benar tersimpan ke disk,
// bukan hanya ke memory. Di Windows key ':' dulu membuat file gagal ditulis senyap.
// Jalankan: php tests/store_file_smoke.php
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

$fail = 0;
function sf_check($label, $cond) {
    global $fail;
    if (!$cond) { $fail++; fwrite(STDERR, "FAIL {$label}\n"); }
}

// 1) Key dengan ':' harus bisa disimpan & dibaca (via memory buffer di proses ini).
$k1 = 'dash:99:w1';
\App\Cache\Store::set($k1, ['v' => 'hello'], 60);
$g1 = \App\Cache\Store::get($k1);
sf_check('set/get key ":" hit', ($g1['hit'] ?? false) === true);
sf_check('set/get key ":" value', ($g1['value']['v'] ?? null) === 'hello');

// 2) File cache harus benar-benar ada di disk (bukti file_put_contents sukses).
//    Store::safe() kini mengganti ':' dengan '-'.
$dir = dirname(__DIR__) . '/storage/cache';
$safe = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $k1);
$file = $dir . '/' . $safe . '.cache';
sf_check('file cache ada di disk', is_file($file));

// 3) forgetPrefix harus membersihkan key ber-':' juga.
\App\Cache\Store::forgetPrefix('dash:99:');
$g2 = \App\Cache\Store::get($k1);
sf_check('forgetPrefix ":" bersih', ($g2['hit'] ?? false) === false);

// 4) Key tanpa karakter khusus tetap bekerja (regresi).
$k2 = 'plain_key_123';
\App\Cache\Store::set($k2, ['n' => 7], 60);
sf_check('key plain hit', (\App\Cache\Store::get($k2)['hit'] ?? false) === true);
\App\Cache\Store::forget($k2);

echo $fail === 0 ? "store file smoke OK\n" : "store file smoke FAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
