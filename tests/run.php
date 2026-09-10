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
check('badge share', badge_share_text('budi', 'Quest Hunter 5'), 'budi meraih badge "Quest Hunter 5" di Learn Tracker');

check('level 899', calculate_level(899), 3);
check('level 900', calculate_level(900), 4);
check('base lv2', level_base_xp(2), 100);
check('base lv4', level_base_xp(4), 900);
check('next dari 400', xp_to_next_level(400), 900);
check('progres 399', level_progress_percent(399), 100);
check('progres 101', level_progress_percent(101), 0);
check('rank lv5', get_user_rank(5), 'Docker Apprentice');
check('rank lv8', get_user_rank(8), 'DevOps Specialist');
check('mult ceil', apply_xp_multiplier(1, 1.5), 2);
check('skill github', normalize_skill('GITHUB actions'), 'Git');
check('skill nginx', normalize_skill('NGINX reverse proxy'), 'Linux');
check('skill ec2', normalize_skill('EC2 instance'), 'AWS');
check('skill blade', normalize_skill('BLADE template'), 'Laravel');
check('skill composer', normalize_skill('COMPOSER install'), 'PHP');
check('skill mariadb', normalize_skill('MARIADB backup'), 'MySQL');
check('week 2', skill_for_week(2), 'PHP');
check('week 5', skill_for_week(5), 'Laravel');
check('week 8', skill_for_week(8), 'Docker');
check('week 9', skill_for_week(9), 'AWS');
check('week 10', skill_for_week(10), 'Linux');
check('week 11', skill_for_week(11), 'Git');
check('week 0 clamp', skill_for_week(0), 'MySQL');
check('week 99 clamp', skill_for_week(99), 'General');
check('clean null', clean(null), '');
check('clean array', clean([]), '');
check('clean trim', clean('  hi  '), 'hi');
check('clean panjang', mb_strlen(clean(str_repeat('x', 6000))), 5000);
check('esc null', esc(null), '');
check('url spasi', valid_url('  https://example.com  '), 'https://example.com');
check('url kosong', valid_url(''), '');
check('frame gold ok', avatar_unlocked('gold', 5, 0, []), true);
check('frame gold belum', avatar_unlocked('gold', 4, 0, []), false);
check('frame legend lv', avatar_unlocked('legend', 8, 0, []), true);
check('frame asing', avatar_unlocked('x', 9, 99, []), false);
check('frame ember kurang', avatar_unlocked('ember', 9, 6, []), false);
check('topik 16', count(quiz_topics()), 16);
check('sm2 upper', sm2_grade_to_int('AGAIN'), 0);
check('sm2 int clamp atas', sm2_grade_to_int(7), 5);
check('sm2 int clamp bawah', sm2_grade_to_int(-2), 0);
check('sm2 default', sm2_grade_to_int('asal'), 4);
check('sm2 hard r0', sm2_next(2.5, 0, 1, 'hard')['interval'], 1);
check('sm2 hard r2', sm2_next(2.5, 2, 6, 'hard')['interval'], 11);
check('sm2 good r2', sm2_next(2.5, 2, 6, 'good')['interval'], 15);
check('sm2 easy r2', sm2_next(2.5, 2, 6, 'easy')['interval'], 19);
check('sm2 cap 90', sm2_next(3.0, 9, 90, 'easy')['interval'], 90);
check('sm2 label 4', count(sm2_labels()), 4);
check('heat 9', analytics_heat_level(9), 1);
check('heat 10', analytics_heat_level(10), 2);
check('heat 24', analytics_heat_level(24), 2);
check('heat 25', analytics_heat_level(25), 3);
check('heat 49', analytics_heat_level(49), 3);
check('heat 50', analytics_heat_level(50), 4);
check('konsistensi nol', analytics_consistency_score(0, 28), 0);
check('trend anjlok', analytics_trend_percent(0, 5), -100);
check('verdict kuat', analytics_streak_verdict(85), 'Ritme kuat. Tinggal jaga.');
check('verdict tumbuh', analytics_streak_verdict(50), 'Ritme tumbuh. Tambah 1 sesi kecil.');
check('verdict rapuh', analytics_streak_verdict(10), 'Ritme rapuh. Satu aksi hari ini cukup.');
check('verdict nol', analytics_streak_verdict(0), 'Belum mulai. Satu sesi 25 menit memecah kebekuan.');
check('proyeksi rate', analytics_project_days_left(5, 10, 2.0), 3);
check('label besok', analytics_predict_label(1), 'Tuntas besok jika ritme dijaga.');
check('label hari', analytics_predict_label(7), 'Tuntas sekitar 7 hari lagi.');
check('label minggu', analytics_predict_label(21), 'Sekitar 3 minggu menuju tuntas.');
check('label bulan', analytics_predict_label(90), 'Sekitar 3 bulan menuju tuntas di ritme ini.');
check('prev acak', quest_prev_map([['id' => 2, 'week' => 2, 'user_id' => null], ['id' => 1, 'week' => 1, 'user_id' => null]])[2], 1);
check('blocker self', quest_blocker(['id' => 3, 'user_id' => null, 'depends_on' => 3], [], 1), null);
check('week kosong', quest_week_stats([]), ['done' => 0, 'total' => 0, 'pct' => 0]);
check('next null', quest_next_unlocked([['id' => 1, 'week' => 1, 'user_id' => null, 'completed_at' => 'x']], [1 => true], [1 => null]), null);
check('challenge w04', challenge_for_week('2026-W04')['target_xp'], 80);
check('challenge w05', challenge_for_week('2026-W05')['target_xp'], 100);
check('challenge nol', challenge_pct(0, 100), 0);
check('cheer spasi', cheer_clean('  a  b  '), 'a b');
check('cheer 140', mb_strlen(cheer_clean(str_repeat('y', 141))), 140);
check('reroll t1', shop_reroll_win(1, 7), 7);
check('reroll t1 clamp', shop_reroll_win(30, 99), 10);
check('reroll t2', shop_reroll_win(75, 15), 15);
check('reroll t2 clamp', shop_reroll_win(61, 3), 11);
check('reroll t3', shop_reroll_win(95, 25), 25);
check('reroll t3 clamp', shop_reroll_win(100, 99), 30);
check('reroll ev', shop_reroll_ev() < 20, true);
check('rl lolos', rate_limit_hit('t_rl_1', 2, 60), false);
check('rl lolos2', rate_limit_hit('t_rl_1', 2, 60), false);
check('rl kena', rate_limit_hit('t_rl_1', 2, 60), true);
check('skill q join', review_skill_for('quest', 'Belajar JOIN database', ''), 'MySQL');
check('skill fallback src', review_skill_for('PHP', 'halo', 'dunia'), 'PHP');

check('bank 66 kartu', count(quiz_bank_cards()), 66);
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

check('combo 0', combo_tier(0), 1.0);
check('combo 1', combo_tier(1), 1.0);
check('combo 2', combo_tier(2), 1.5);
check('combo 3', combo_tier(3), 2.0);
check('combo 9', combo_tier(9), 2.0);
check('combo count', combo_count_done(['a' => ['done' => true], 'b' => ['done' => false], 'c' => ['done' => true]]), 2);
check('combo label', \App\Domain\Gamification\Combo::label(1.5), 'x1.5');
check('combo hint max', \App\Domain\Gamification\Combo::nextHint(3), 'Kombo maks!');
check('loot 3 item', count(\App\Domain\Shop::lootFrames()), 3);
check('loot aurora sept', \App\Domain\Shop::lootAvailable(\App\Domain\Shop::lootByFrame('aurora'), 9), true);
check('loot aurora okt', \App\Domain\Shop::lootAvailable(\App\Domain\Shop::lootByFrame('aurora'), 10), false);
check('loot asing', \App\Domain\Shop::lootByFrame('x'), null);
check('frame loot terkunci', avatar_unlocked('aurora', 9, 99, [], false, []), false);
check('frame loot milik', avatar_unlocked('aurora', 1, 0, [], false, ['aurora']), true);
check('next klaim', \App\Domain\NextAction::pick(['claimable_n' => 2, 'claimable_xp' => 10, 'due_reviews' => 3, 'next_quest' => ['id' => 1, 'title' => 'Q', 'xp_reward' => 5], 'pomo_today' => 0])['type'], 'claim');
check('next review', \App\Domain\NextAction::pick(['claimable_n' => 0, 'due_reviews' => 2, 'next_quest' => ['id' => 1, 'title' => 'Q', 'xp_reward' => 5], 'pomo_today' => 0])['type'], 'review');
check('next quest', \App\Domain\NextAction::pick(['claimable_n' => 0, 'due_reviews' => 0, 'next_quest' => ['id' => 7, 'title' => 'Lanjut', 'xp_reward' => 20], 'pomo_today' => 1])['type'], 'quest');
check('next quest id', \App\Domain\NextAction::pick(['next_quest' => ['id' => 7, 'title' => 'Lanjut', 'xp_reward' => 20]])['quest_id'], 7);
check('next fokus', \App\Domain\NextAction::pick(['claimable_n' => 0, 'due_reviews' => 0, 'pomo_today' => 0])['type'], 'focus');
check('next digest', \App\Domain\NextAction::pick(['claimable_n' => 0, 'due_reviews' => 0, 'pomo_today' => 2])['type'], 'digest');
check('next recovery', \App\Domain\NextAction::pick(['claimable_n' => 0, 'streak_broken' => true, 'due_reviews' => 5, 'pomo_today' => 0])['type'], 'recovery');
check('next klaim dulu', \App\Domain\NextAction::pick(['claimable_n' => 1, 'claimable_xp' => 5, 'streak_broken' => true])['type'], 'claim');
check('bonus genap', \App\Domain\Gamification\Missions::bonusForDate(strtotime('2026-09-02'))['key'], 'bonus_full');
check('bonus ganjil', \App\Domain\Gamification\Missions::bonusForDate(strtotime('2026-09-03'))['key'], 'bonus_focus2');
check('mastery lv1', \App\Domain\Skill\Mastery::level(0), 1);
check('mastery lv2', \App\Domain\Skill\Mastery::level(50), 2);
check('mastery lv2b', \App\Domain\Skill\Mastery::level(99), 2);
check('mastery need', \App\Domain\Skill\Mastery::need(30), 20);
check('mastery need pas', \App\Domain\Skill\Mastery::need(50), 50);
check('mastery node', \App\Domain\Skill\Mastery::nodeForSkill('Docker'), 'docker');
check('mastery node net', \App\Domain\Skill\Mastery::nodeForSkill('Networking'), 'networking');
check('mastery node general', \App\Domain\Skill\Mastery::nodeForSkill('General'), null);
check('mastery node testing', \App\Domain\Skill\Mastery::nodeForSkill('Testing'), 'testing');
check('track default', \App\Domain\Track\Tracks::normalize('asal'), 'devops');
check('track rpl', \App\Domain\Track\Tracks::normalize('RPL'), 'rpl');
check('track weeks devops w7', \App\Domain\Track\Tracks::weeks('devops')[7], 'Docker');
check('track weeks rpl w7', \App\Domain\Track\Tracks::weeks('rpl')[7], 'Git');
check('track weeks rpl w8', \App\Domain\Track\Tracks::weeks('rpl')[8], 'Testing');
check('skill week rpl', skill_for_week(8, 'rpl'), 'Testing');
check('skill week devops', skill_for_week(8, 'devops'), 'Docker');
check('skill week default', skill_for_week(8), 'Docker');
check('skill defs rpl', array_keys(skill_defs('rpl')), ['Git','MySQL','PHP','Laravel','Testing','General']);
check('onboarding target rpl', onboarding_targets('rpl'), ['Backend Engineer','Frontend Engineer','Fullstack Engineer','QA Engineer','Masih ragu']);
check('onboarding target devops', onboarding_targets('devops')[0], 'DevOps Engineer');
check('quiz topics rpl', quiz_topics('rpl'), ['Git','MySQL','PHP','Laravel','Testing','General']);
check('quiz topics devops', quiz_topics('devops'), ['Linux','Git','MySQL','PHP','Laravel','Docker','AWS','Networking','General']);
check('normalize testing', normalize_skill('phpunit test'), 'Testing');
check('track tkj', \App\Domain\Track\Tracks::normalize('TKJ'), 'tkj');
check('track weeks tkj w1', \App\Domain\Track\Tracks::weeks('tkj')[1], 'Networking');
check('track weeks tkj w2', \App\Domain\Track\Tracks::weeks('tkj')[2], 'Linux');
check('skill week tkj', skill_for_week(1, 'tkj'), 'Networking');
check('skill defs tkj', array_keys(skill_defs('tkj')), ['Linux','Git','Docker','AWS','Networking','General']);
check('onboarding target tkj', onboarding_targets('tkj'), ['Network Engineer','System Administrator','Cloud Engineer','Security Analyst','Masih ragu']);
check('quiz topics tkj', quiz_topics('tkj'), ['Networking','Linux','Docker','AWS','Git','General']);
check('normalize networking', normalize_skill('subnet 192.168.1.0/24'), 'Networking');
check('mastery node networking', \App\Domain\Skill\Mastery::nodeForSkill('Networking'), 'networking');
check('track dkv', \App\Domain\Track\Tracks::normalize('DKV'), 'dkv');
check('track weeks dkv w1', \App\Domain\Track\Tracks::weeks('dkv')[1], 'Desain');
check('track weeks dkv w5', \App\Domain\Track\Tracks::weeks('dkv')[5], 'UI/UX');
check('skill week dkv', skill_for_week(1, 'dkv'), 'Desain');
check('skill defs dkv', array_keys(skill_defs('dkv')), ['Desain','Tipografi','Branding','UI/UX','Ilustrasi','Motion','General']);
check('onboarding target dkv', onboarding_targets('dkv'), ['UI/UX Designer','Graphic Designer','Brand Designer','Motion Designer','Masih ragu']);
check('quiz topics dkv', quiz_topics('dkv'), ['Desain','Tipografi','Branding','UI/UX','Ilustrasi','Motion','General']);
check('normalize branding', normalize_skill('logo brand identity'), 'Branding');
check('normalize uiux', normalize_skill('wireframe figma'), 'UI/UX');
check('mastery node desain', \App\Domain\Skill\Mastery::nodeForSkill('Tipografi'), 'desain');
check('mastery node uiux', \App\Domain\Skill\Mastery::nodeForSkill('UI/UX'), 'uiux');
check('mastery node motion', \App\Domain\Skill\Mastery::nodeForSkill('Motion'), 'motion');
check('incident 16 lab', count(\App\Domain\Incident\IncidentBank::all()), 16);
$incSlugs = array_column(\App\Domain\Incident\IncidentBank::all(), 'slug');
check('incident unik', count($incSlugs) === count(array_unique($incSlugs)), true);
$incFree = count(array_filter(\App\Domain\Incident\IncidentBank::all(), fn($c) => empty($c['is_pro'])));
check('incident gratis 2', $incFree, 2);
$incSkills = array_unique(array_column(\App\Domain\Incident\IncidentBank::all(), 'skill'));
foreach (['MySQL', 'Git', 'Laravel'] as $s) check("incident skill $s", in_array($s, $incSkills, true), true);
foreach (['Networking', 'Linux'] as $s) check("incident tkj $s", in_array($s, $incSkills, true), true);
check('rubrik 5 kriteria', count(\App\Domain\Dkv\Rubric::criteria()), 5);
check('rubrik bobot 100', array_sum(array_column(\App\Domain\Dkv\Rubric::criteria(), 'weight')), 100);
check('rubrik avg penuh', \App\Domain\Dkv\Rubric::weightedAvg(['konsep' => 5, 'tipografi' => 5, 'warna' => 5, 'layout' => 5, 'presentasi' => 5]), 5.0);
check('rubrik avg campur', \App\Domain\Dkv\Rubric::weightedAvg(['konsep' => 4, 'tipografi' => 4, 'warna' => 4, 'layout' => 4, 'presentasi' => 4]), 4.0);
check('rubrik avg kosong', \App\Domain\Dkv\Rubric::weightedAvg([]), 0.0);
check('rubrik clamp', \App\Domain\Dkv\Rubric::clampScore(9), 5);
check('rubrik clamp bawah', \App\Domain\Dkv\Rubric::clampScore(-2), 1);
check('karya tolak kosong', \App\Domain\Dkv\Karya::validate([])['ok'], false);
check('karya maks 3mb', \App\Domain\Dkv\Karya::MAX_BYTES, 3145728);
check('karya tipe allowed', count(\App\Domain\Dkv\Karya::ALLOWED), 4);
$cardTopics = array_unique(array_map(fn($c) => $c[0], quiz_bank_cards()));
foreach (['devops', 'rpl', 'tkj', 'dkv'] as $tr) {
    foreach (quiz_topics($tr) as $tt) {
        if ($tt === 'General') continue;
        check("bank cover $tr:$tt", in_array($tt, $cardTopics, true), true);
    }
}
check('mentor 3 rec', count(\App\Domain\Mentor::recommend(['track' => 'rpl', 'due_reviews' => 2, 'incident_tries' => 0, 'incident_avg' => 0, 'quest_pct' => 40])), 3);
check('mentor recovery', \App\Domain\Mentor::recommend(['track' => 'tkj', 'streak_broken' => true, 'quest_pct' => 100])[0]['reason'], 'recovery');
check('mentor gap', in_array('gap', array_column(\App\Domain\Mentor::recommend(['track' => 'dkv', 'skill_gap' => 'Tipografi', 'quest_pct' => 50]), 'reason')), true);
check('lab 9 total', count(\App\Domain\Lab\LabBank::all()), 9);
check('lab rpl 3', count(\App\Domain\Lab\LabBank::forTrack('rpl')), 3);
check('lab tkj 3', count(\App\Domain\Lab\LabBank::forTrack('tkj')), 3);
check('lab dkv 3', count(\App\Domain\Lab\LabBank::forTrack('dkv')), 3);
check('lab mcq benar', \App\Domain\Lab\LabBank::grade(\App\Domain\Lab\LabBank::find('rpl-output-php'), '1'), true);
check('lab mcq salah', \App\Domain\Lab\LabBank::grade(\App\Domain\Lab\LabBank::find('rpl-output-php'), '0'), false);
check('lab calc benar', \App\Domain\Lab\LabBank::grade(\App\Domain\Lab\LabBank::find('tkj-subnet-dasar'), '254'), true);
check('lab calc salah', \App\Domain\Lab\LabBank::grade(\App\Domain\Lab\LabBank::find('tkj-subnet-dasar'), '255'), false);
check('lab sponsor ada', in_array(true, array_map(fn($l) => !empty($l['sponsor']), \App\Domain\Lab\LabBank::all())), true);
check('brief 6', count(\App\Domain\Dkv\Brief::all()), 6);
check('brief minggu 5', \App\Domain\Dkv\Brief::forWeek(5)['slug'], 'landing-ppdb');
check('pro school plan', \App\Domain\Pro::plan('school')['price'], 99000);
check('pro school fitur', count(\App\Domain\Pro::features('school')), 4);
check('pg tasks 4', count(\App\Domain\Playground::tasks()), 4);
check('pg php aman', \App\Domain\Playground::safePhpOutput('$a = 5; echo $a + 3;')['output'], '8');
check('pg php tolak eval', \App\Domain\Playground::safePhpOutput('eval("x")')['ok'], false);
check('pg php tolak system', \App\Domain\Playground::safePhpOutput('system("ls")')['ok'], false);
check('pg php ternary', \App\Domain\Playground::safePhpOutput('echo 5 >= 3 ? "Y" : "N";')['output'], 'Y');
check('pg grade php', \App\Domain\Playground::grade('php-diskon', '$harga = 100000; $diskon = 10; echo $harga - ($harga * $diskon / 100);'), true);
check('pg grade sql', \App\Domain\Playground::grade('sql-stok', 'stok > 10'), true);
check('pg sql and', implode(',', \App\Domain\Playground::filterIds('stok > 10 AND harga < 100000')), '3');
check('pg js grade', \App\Domain\Playground::grade('js-loop', '15'), true);
check('topo /24 hosts', \App\Domain\Tkj\Topo::parse('192.168.1.0/24')['hosts'], 254);
check('topo network', \App\Domain\Tkj\Topo::parse('192.168.1.0/24')['network'], '192.168.1.0');
check('topo broadcast', \App\Domain\Tkj\Topo::parse('192.168.1.0/24')['broadcast'], '192.168.1.255');
check('topo private', \App\Domain\Tkj\Topo::parse('10.0.0.0/8')['private'], true);
check('topo tolak', \App\Domain\Tkj\Topo::parse('asal')['ok'], false);
check('term help', strpos(\App\Domain\Tkj\Terminal::exec([], 'help')['out'], 'pwd') !== false, true);
check('term unknown', strpos(\App\Domain\Tkj\Terminal::exec([], 'rm -rf /')['out'], 'unknown') !== false, true);
$ts = \App\Domain\Tkj\Terminal::exec([], 'chown -R www-data:www-data /var/www')['state'];
$ts = \App\Domain\Tkj\Terminal::exec($ts, 'chmod 755 /var/www')['state'];
check('term misi www', \App\Domain\Tkj\Terminal::missionDone($ts, \App\Domain\Tkj\Terminal::find('fix-www')), true);
check('term belum', \App\Domain\Tkj\Terminal::missionDone([], \App\Domain\Tkj\Terminal::find('fix-www')), false);
check('critique valid', \App\Domain\Dkv\Critique::valid('Bagus, rapikan spacing'), true);
check('critique pendek', \App\Domain\Dkv\Critique::valid('ok'), false);
check('sponsor clean', \App\Domain\Sponsor::clean('  Studio  X  '), 'Studio X');
foreach (['rpl', 'tkj', 'dkv'] as $tr) {
    $rq = \App\Domain\Track\Roadmap::quests($tr);
    check("roadmap $tr 12", count($rq), 12);
    $weeks = array_unique(array_map(fn($r) => $r[0], $rq));
    sort($weeks);
    check("roadmap $tr minggu 1-12", $weeks, range(1, 12));
    $titles = array_map(fn($r) => mb_strtolower($r[1]), $rq);
    check("roadmap $tr judul unik", count($titles) === count(array_unique($titles)), true);
}
check('roadmap devops kosong', \App\Domain\Track\Roadmap::quests('devops'), []);
check('roadmap normalize asal', \App\Domain\Track\Roadmap::quests('asal'), []);

// Test Tracks route permissions and feature isolation
check('guard topologi tkj', \App\Domain\Track\Tracks::isPageAllowed('topologi.php', 'tkj'), true);
check('guard topologi dkv block', \App\Domain\Track\Tracks::isPageAllowed('topologi.php', 'dkv'), false);
check('guard topologi rpl block', \App\Domain\Track\Tracks::isPageAllowed('topologi.php', 'rpl'), false);

check('guard brief dkv', \App\Domain\Track\Tracks::isPageAllowed('brief.php', 'dkv'), true);
check('guard brief rpl block', \App\Domain\Track\Tracks::isPageAllowed('brief.php', 'rpl'), false);
check('guard brief devops block', \App\Domain\Track\Tracks::isPageAllowed('brief.php', 'devops'), false);

check('guard incident devops', \App\Domain\Track\Tracks::isPageAllowed('incident.php', 'devops'), true);
check('guard incident tkj', \App\Domain\Track\Tracks::isPageAllowed('incident.php', 'tkj'), true);
check('guard incident dkv block', \App\Domain\Track\Tracks::isPageAllowed('incident.php', 'dkv'), false);
check('guard incident rpl block', \App\Domain\Track\Tracks::isPageAllowed('incident.php', 'rpl'), false);

check('guard playground rpl', \App\Domain\Track\Tracks::isPageAllowed('playground.php', 'rpl'), true);
check('guard playground devops', \App\Domain\Track\Tracks::isPageAllowed('playground.php', 'devops'), true);
check('guard playground dkv block', \App\Domain\Track\Tracks::isPageAllowed('playground.php', 'dkv'), false);

check('guard general quests open', \App\Domain\Track\Tracks::isPageAllowed('quests.php', 'dkv'), true);

// Test Primary Features per track
check('feat rpl', \App\Domain\Track\Tracks::primaryFeature('rpl')['href'], 'playground.php');
check('feat tkj', \App\Domain\Track\Tracks::primaryFeature('tkj')['href'], 'topologi.php');
check('feat dkv', \App\Domain\Track\Tracks::primaryFeature('dkv')['href'], 'quests.php');
check('feat devops', \App\Domain\Track\Tracks::primaryFeature('devops')['href'], 'incident.php');

// Test Track Curated Resources
foreach (['rpl', 'tkj', 'dkv'] as $tr) {
    $res = \App\Domain\Track\Roadmap::resources($tr);
    check("resources $tr count", count($res), 36);
    $types = array_unique(array_column($res, 2));
    sort($types);
    check("resources $tr types", $types, ['dokumentasi', 'praktek', 'video']);
}

echo "pass: {$pass}, fail: {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
