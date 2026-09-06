<?php
// Tes logika murni (tanpa DB). Jalankan: php tests/run.php
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

$pass = 0; $fail = 0;
function check($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; }
    else { $fail++; fwrite(STDERR, "FAIL {$label}: got " . var_export($actual, true) . ", want " . var_export($expected, true) . PHP_EOL); }
}

check('level 0 xp', calculate_level(0), 1);
check('level 99 xp', calculate_level(99), 1);
check('level 100 xp', calculate_level(100), 2);
check('level 399 xp', calculate_level(399), 2);
check('level 400 xp', calculate_level(400), 3);
check('level negatif', calculate_level(-50), 1);
check('base lv1', level_base_xp(1), 0);
check('base lv3', level_base_xp(3), 400);
check('next dari 0', xp_to_next_level(0), 100);
check('next dari 100', xp_to_next_level(100), 400);
check('progres 0', level_progress_percent(0), 0);
check('progres 50', level_progress_percent(50), 50.0);
check('progres 100', level_progress_percent(100), 0);
check('rank lv1', get_user_rank(1), 'Terminal Cadet');
check('rank lv12', get_user_rank(12), 'DevOps Legend');
check('rank clamp', get_user_rank(99), 'DevOps Legend');

check('interval 0', review_next_interval(0), 1);
check('interval 1', review_next_interval(1), 3);
check('interval 3', review_next_interval(3), 7);
check('interval 7', review_next_interval(7), 14);
check('interval 14', review_next_interval(14), 30);
check('interval 30', review_next_interval(30), 30);
check('interval 99', review_next_interval(99), 30);

check('cap penuh', capped_xp_gain(2, 0, 20), 2);
check('cap sisa 1', capped_xp_gain(2, 19, 20), 1);
check('cap habis', capped_xp_gain(2, 20, 20), 0);
check('cap lewat', capped_xp_gain(2, 99, 20), 0);
check('cap want 0', capped_xp_gain(0, 0, 20), 0);

check('mult 1.5', apply_xp_multiplier(5, 1.5), 8);
check('mult 1.0', apply_xp_multiplier(5, 1.0), 5);

check('skill mysql', normalize_skill('MySQL error 1064'), 'MySQL');
check('skill docker', normalize_skill('Dockerfile build'), 'Docker');
check('skill kosong', normalize_skill(''), '');
check('skill asing', normalize_skill('memasak'), 'General');
check('week 1', skill_for_week(1), 'MySQL');
check('week 7', skill_for_week(7), 'Docker');
check('week 12', skill_for_week(12), 'General');

check('clean slash', clean('a\\b'), 'ab');
check('esc quote', esc('"<b>"'), '&quot;&lt;b&gt;&quot;');
check('url ok', valid_url('https://example.com/x'), 'https://example.com/x');
check('url js', valid_url('javascript:alert(1)'), '');
check('url ftp', valid_url('ftp://example.com'), '');

check('frame owner', avatar_unlocked('legend', 1, 0, [], true), true);
check('frame default', avatar_unlocked('default', 1, 0, []), true);
check('frame ring belum', avatar_unlocked('ring', 2, 0, []), false);
check('frame ring ok', avatar_unlocked('ring', 3, 0, []), true);
check('frame ember', avatar_unlocked('ember', 1, 7, []), true);
check('frame legend badge', avatar_unlocked('legend', 1, 0, ['quest-all' => []]), true);

check('trend naik', analytics_trend_percent(150, 100), 50);
check('trend turun', analytics_trend_percent(50, 100), -50);
check('trend nol', analytics_trend_percent(0, 0), 0);
check('trend dari nol', analytics_trend_percent(30, 0), 100);
check('konsistensi penuh', analytics_consistency_score(28, 28), 100);
check('konsistensi setengah', analytics_consistency_score(14, 28), 50);
check('konsistensi clamp', analytics_consistency_score(99, 28), 100);
check('heat 0', analytics_heat_level(0), 0);
check('heat 1', analytics_heat_level(5), 1);
check('heat 2', analytics_heat_level(15), 2);
check('heat 3', analytics_heat_level(30), 3);
check('heat 4', analytics_heat_level(80), 4);
check('proyeksi tuntas', analytics_project_days_left(14, 14, 1.0), 0);
check('proyeksi belum jalan', analytics_project_days_left(0, 14, 0), -1);
check('proyeksi 7 hari', analytics_project_days_left(7, 14, 1.0), 7);
check('label tuntas', analytics_predict_label(0), 'Roadmap tuntas. Pertahankan dengan review.');
check('label butuh aksi', analytics_predict_label(-1), 'Selesaikan 1 quest untuk memproyeksi target.');
check('sm2 lagi', sm2_next(2.5, 2, 6, 'again')['interval'], 1);
check('sm2 lagi reps', sm2_next(2.5, 2, 6, 'again')['reps'], 0);
check('sm2 good pertama', sm2_next(2.5, 0, 1, 'good')['interval'], 1);
check('sm2 good kedua', sm2_next(2.5, 1, 1, 'good')['interval'], 6);
check('sm2 easy tambah', sm2_next(2.5, 2, 6, 'easy')['interval'] > 6, true);
check('sm2 hard lolos', sm2_next(2.5, 2, 6, 'hard')['reps'], 3);
check('sm2 map know', sm2_grade_to_int('know'), 4);
check('sm2 map forgot', sm2_grade_to_int('forgot'), 0);
check('sm2 ease batas', sm2_next(1.2, 5, 30, 'good')['ease'] >= 1.3, true);
check('skill review docker', review_skill_for('quiz', 'Dockerfile build error', ''), 'Docker');
$chain = [['id' => 1, 'week' => 1, 'user_id' => null], ['id' => 2, 'week' => 2, 'user_id' => null], ['id' => 9, 'week' => 2, 'user_id' => 7]];
check('prev pertama null', quest_prev_map($chain)[1], null);
check('prev kedua', quest_prev_map($chain)[2], 1);
check('prev custom null', quest_prev_map($chain)[9], null);
check('blocker awal', quest_blocker(['id' => 2, 'user_id' => null, 'depends_on' => null], [], 1), 1);
check('blocker lolos', quest_blocker(['id' => 2, 'user_id' => null, 'depends_on' => null], [1 => true], 1), null);
check('blocker eksplisit', quest_blocker(['id' => 5, 'user_id' => null, 'depends_on' => 3], [1 => true], 1), 3);
check('blocker custom bebas', quest_blocker(['id' => 9, 'user_id' => 7, 'depends_on' => null], [], 1), null);
check('blocker done bebas', quest_blocker(['id' => 1, 'user_id' => null, 'depends_on' => null, 'completed_at' => '2026-01-01'], [], null), null);
check('week stats', quest_week_stats([['completed_at' => 'x'], []]), ['done' => 1, 'total' => 2, 'pct' => 50]);
check('next unlocked skip', quest_next_unlocked([['id' => 1, 'week' => 1, 'user_id' => null], ['id' => 2, 'week' => 2, 'user_id' => null]], [], [1 => null, 2 => 1])['id'], 1);
check('week key format', (bool)preg_match('/^\d{4}-W\d{2}$/', challenge_week_key()), true);
check('challenge target', challenge_for_week('2026-W01')['target_xp'] >= 80, true);
check('challenge pct penuh', challenge_pct(150, 100), 100);
check('challenge pct setengah', challenge_pct(50, 100), 50);
check('cheer ok', cheer_clean('  Semangat ya!  '), 'Semangat ya!');
check('cheer pendek', cheer_clean('a'), '');
check('cheer panjang', mb_strlen(cheer_clean(str_repeat('x', 200))) <= 140, true);
check('badge share', badge_share_text('budi', 'Quest Hunter 5'), 'budi meraih badge "Quest Hunter 5" di Learn Tracker DevOps');

check('bank 42 kartu', count(quiz_bank_cards()), 42);
$bank_ok = true;
foreach (quiz_bank_cards() as $c) {
    if (!is_array($c) || count($c) !== 3 || trim((string)$c[1]) === '' || trim((string)$c[2]) === '') { $bank_ok = false; break; }
}
check('bank format', $bank_ok, true);
$topics_ok = true;
foreach (quiz_bank_cards() as $c) {
    if (!in_array($c[0], quiz_topics(), true)) { $topics_ok = false; break; }
}
check('bank topik valid', $topics_ok, true);

echo "pass: {$pass}, fail: {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
