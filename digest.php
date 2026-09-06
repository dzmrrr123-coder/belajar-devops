<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

$xp_now = 0; $xp_prev = 0; $fs = 0; $fm = 0; $qd = 0; $notes = 0; $due = 0;
try {
    $s = $conn->prepare("SELECT (SELECT COALESCE(SUM(amount),0) FROM xp_events WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 6 DAY) AS now_xp, (SELECT COALESCE(SUM(amount),0) FROM xp_events WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 13 DAY AND created_at < CURDATE() - INTERVAL 6 DAY) AS prev_xp, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= CURDATE() - INTERVAL 6 DAY) AS fs, (SELECT COALESCE(SUM(duration_minutes),0) FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= CURDATE() - INTERVAL 6 DAY) AS fm, (SELECT COUNT(*) FROM user_quests WHERE user_id = ? AND completed_at >= CURDATE() - INTERVAL 6 DAY) AS qd, (SELECT COUNT(*) FROM errors WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 6 DAY) + (SELECT COUNT(*) FROM questions WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 6 DAY) AS n, (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due <= CURDATE()) AS due");
    $s->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc() ?: [];
    $s->close();
    $xp_now = (int)($r['now_xp'] ?? 0);
    $xp_prev = (int)($r['prev_xp'] ?? 0);
    $fs = (int)($r['fs'] ?? 0); $fm = (int)($r['fm'] ?? 0); $qd = (int)($r['qd'] ?? 0);
    $notes = (int)($r['n'] ?? 0);
    $due = (int)($r['due'] ?? 0);
} catch (Throwable $e) {}

$daily = [];
for ($i = 83; $i >= 0; $i--) {
    $k = date('Y-m-d', strtotime("-{$i} days"));
    $daily[$k] = 0;
}
try {
    $s = $conn->prepare("SELECT DATE(created_at) d, COALESCE(SUM(amount),0) s FROM xp_events WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 83 DAY GROUP BY DATE(created_at)");
    $s->bind_param("i", $user_id);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $k = (string)($row['d'] ?? '');
        if (isset($daily[$k])) $daily[$k] = max(0, (int)$row['s']);
    }
    $s->close();
} catch (Throwable $e) {}

$by_reason = [];
try {
    $s = $conn->prepare("SELECT reason, COALESCE(SUM(amount),0) s FROM xp_events WHERE user_id = ? AND amount > 0 AND created_at >= CURDATE() - INTERVAL 27 DAY GROUP BY reason ORDER BY s DESC LIMIT 6");
    $s->bind_param("i", $user_id);
    $s->execute();
    $by_reason = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
} catch (Throwable $e) {}
$reason_max = 1;
foreach ($by_reason as $br) $reason_max = max($reason_max, (int)($br['s'] ?? 0));

$weeks = [];
for ($w = 11; $w >= 0; $w--) $weeks[$w] = 0;
$vals = array_values($daily);
for ($d = 0; $d < 84; $d++) {
    $w = (int)floor((83 - $d) / 7);
    if ($w >= 0 && $w <= 11) $weeks[11 - $w] += (int)($vals[$d] ?? 0);
}
$weeks = array_reverse(array_values($weeks));
$week_max = max(1, max($weeks));

$last28 = array_slice($vals, -28);
$active28 = count(array_filter($last28, fn($v) => (int)$v > 0));
$score28 = analytics_consistency_score($active28, 28);
$verdict28 = analytics_streak_verdict($score28);

$strongest = '—'; $attention = '—'; $queue = []; $skill_rows = [];
$total_cnt = 0; $total_done = 0;
try {
    $s = $conn->prepare("SELECT q.id, q.user_id AS owner, q.week, q.title, q.xp_reward, q.depends_on, (uq.quest_id IS NOT NULL) AS done FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE (q.user_id IS NULL OR q.user_id = ?) ORDER BY q.week ASC, q.id ASC");
    $s->bind_param("ii", $user_id, $user_id);
    $s->execute();
    $allq = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    $pmap = quest_prev_map(array_map(fn($r) => ['id' => $r['id'], 'week' => $r['week'], 'user_id' => $r['owner']], $allq));
    $doneset = [];
    foreach ($allq as $r) if (!empty($r['done'])) $doneset[(int)$r['id']] = true;
    $per = [];
    foreach ($allq as $r) {
        $total_cnt++;
        $sk = skill_for_week((int)$r['week']);
        $per[$sk] = $per[$sk] ?? ['done' => 0, 'total' => 0];
        $per[$sk]['total']++;
        if (!empty($r['done'])) {
            $total_done++;
            $per[$sk]['done']++;
        } elseif (count($queue) < 3) {
            $blk = quest_blocker(['id' => $r['id'], 'user_id' => $r['owner'], 'depends_on' => $r['depends_on']], $doneset, $pmap[(int)$r['id']] ?? null);
            if ($blk === null) $queue[] = ['title' => (string)$r['title'], 'xp' => (int)$r['xp_reward']];
        }
    }
    $best = -1; $most_left = -1;
    foreach ($per as $name => $p) {
        $pct = $p['total'] > 0 ? (int)round($p['done'] / $p['total'] * 100) : 0;
        $skill_rows[] = ['name' => $name, 'done' => $p['done'], 'total' => $p['total'], 'pct' => $pct];
        if ($p['done'] > $best) { $best = $p['done']; $strongest = $name; }
        if (($p['total'] - $p['done']) > $most_left) { $most_left = $p['total'] - $p['done']; $attention = $name; }
    }
    usort($skill_rows, fn($a, $b) => $a['pct'] <=> $b['pct']);
    if ($best <= 0) $strongest = '—';
    if ($most_left <= 0) $attention = '—';
} catch (Throwable $e) {}
$conn->close();

$delta = analytics_trend_percent($xp_now, $xp_prev);
$avg14 = array_sum(array_slice($vals, -14)) / 14;
$quest_rate = $qd / 7;
$fallback_rate = $avg14 > 0 ? $avg14 / 20 : 0;
$days_left = analytics_project_days_left($total_done, max($total_cnt, 14), $quest_rate > 0 ? $quest_rate : $fallback_rate);
$predict = analytics_predict_label($days_left);
$road_pct = $total_cnt > 0 ? (int)round($total_done / $total_cnt * 100) : 0;

if ($xp_now === 0 && $fs === 0 && $qd === 0) {
    $verdict = 'Minggu yang sunyi. Satu sesi fokus 25 menit cukup untuk memecah kebekuan.';
} elseif ($delta > 0) {
    $verdict = "Naik {$delta}% dari minggu lalu. Pertahankan ritmenya.";
} elseif ($delta < 0) {
    $verdict = "Turun " . abs($delta) . "% dari minggu lalu. Tidak apa — mulai kecil hari ini.";
} else {
    $verdict = 'Stabil seperti minggu lalu. Konsisten adalah kemenangan.';
}

$quiet_week = ($xp_now === 0 && $fs === 0 && $qd === 0);
if ($due > 0) {
    $cta_href = 'review.php'; $cta_icon = 'fa-rotate-right'; $cta_label = "Sikat {$due} review";
    $cta_sub = '2 menit, biar tak menumpuk';
} elseif ($quiet_week) {
    $cta_href = 'quests.php'; $cta_icon = 'fa-map'; $cta_label = 'Mulai quest pertama';
    $cta_sub = '15 menit, langsung dapat XP';
} elseif (!empty($queue)) {
    $cta_href = 'quests.php'; $cta_icon = 'fa-map'; $cta_label = 'Lanjut: ' . mb_strimwidth($queue[0]['title'], 0, 40, '...');
    $cta_sub = '+' . (int)$queue[0]['xp'] . ' XP menanti';
} else {
    $cta_href = 'timer.php'; $cta_icon = 'fa-play'; $cta_label = 'Mulai sesi fokus';
    $cta_sub = '25 menit · +10 XP';
}
$delta_bad = $delta < 0;
$reason_labels = ['quest' => 'Quest', 'focus' => 'Fokus', 'note' => 'Catatan', 'quiz' => 'Kuis', 'review' => 'Review', 'mission' => 'Misi', 'chest' => 'Peti', 'other' => 'Lainnya'];
$page_title = 'Ringkasan Mingguan';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" id="main" role="main">
    <div class="page-head">
        <div class="page-kicker">7 hari terakhir · vs minggu lalu · 84 hari konsistensi</div>
        <h1 class="page-title">Ringkasan mingguan</h1>
        <p class="page-desc">Ritme belajarmu minggu ini vs minggu lalu — plus satu aksi berikutnya.</p>
    </div>

    <section class="card p-4 mb-3" aria-label="Vonis minggu ini" style="border-left:4px solid <?= $delta_bad ? 'var(--danger)' : 'var(--primary)' ?>;">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div style="font-size:2rem;font-weight:800;line-height:1;" class="<?= $delta_bad ? 'text-danger' : 'text-emerald' ?>"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?>%</div>
            <div class="flex-grow-1" style="min-width:200px;">
                <strong><?= htmlspecialchars($verdict) ?></strong>
                <div class="text-secondary small">+<?= $xp_now ?> XP · <?= $fs ?> sesi fokus · <?= $qd ?> quest · <?= $notes ?> catatan minggu ini</div>
            </div>
            <a href="<?= $cta_href ?>" class="btn btn-cyber"><i class="fas <?= $cta_icon ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($cta_label) ?></a>
        </div>
        <div class="text-secondary small mt-1"><?= htmlspecialchars($cta_sub) ?></div>
        <div class="ana-predict" aria-label="Proyeksi roadmap">
            <span class="ana-predict-bar" role="progressbar" aria-valuenow="<?= $road_pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres roadmap <?= $road_pct ?> persen"><span style="width:<?= $road_pct ?>%"></span></span>
            <span><strong><?= $total_done ?>/<?= max($total_cnt, 14) ?></strong> quest (<?= $road_pct ?>%) · <?= htmlspecialchars($predict) ?></span>
        </div>
    </section>

    <section class="card p-4 mb-3" aria-labelledby="ana-heat-h">
        <div class="ana-head">
            <div>
                <h2 id="ana-heat-h">Konsistensi 84 hari</h2>
                <p><?= $active28 ?> dari 28 hari aktif (skor <?= $score28 ?>%) · <?= htmlspecialchars($verdict28) ?></p>
            </div>
            <span class="skill-lv"><?= $score28 ?>% konsisten</span>
        </div>
        <div class="heatmap" role="img" aria-label="Heatmap aktivitas 84 hari, <?= $active28 ?> hari aktif dalam 28 hari terakhir">
            <?php $di = 0; foreach ($daily as $date => $xpv): $lvl = analytics_heat_level($xpv); $di++; ?>
            <span class="heat lvl-<?= $lvl ?>" title="<?= $date ?> · +<?= (int)$xpv ?> XP" aria-hidden="true"></span>
            <?php endforeach; ?>
        </div>
        <div class="ana-legend" aria-hidden="true"><span>Kurang</span><span class="heat lvl-0"></span><span class="heat lvl-1"></span><span class="heat lvl-2"></span><span class="heat lvl-3"></span><span class="heat lvl-4"></span><span>Lebih</span></div>
        <details class="ana-more">
            <summary>Lihat 7 hari terakhir</summary>
            <div class="ana-days">
                <?php foreach (array_slice(array_keys($daily), -7) as $dk): ?>
                <span><strong><?= date('d M', strtotime($dk)) ?></strong> +<?= (int)$daily[$dk] ?> XP</span>
                <?php endforeach; ?>
            </div>
        </details>
    </section>

    <section class="card p-4 mb-3" aria-labelledby="ana-week-h">
        <div class="ana-head">
            <div>
                <h2 id="ana-week-h">Grafik 12 minggu</h2>
                <p>XP per minggu · puncak <?= (int)$week_max ?> XP</p>
            </div>
        </div>
        <div class="chart-bars" role="img" aria-label="Grafik XP 12 minggu terakhir">
            <?php foreach ($weeks as $wi => $wv): $h = max(6, (int)round($wv / $week_max * 88)); ?>
            <div class="chart-col">
                <span class="chart-val"><?= $wv > 0 ? (int)$wv : '' ?></span>
                <span class="chart-bar" style="height:<?= $h ?>px;<?= $wi === 11 ? 'outline:2px solid var(--primary);outline-offset:1px;' : '' ?>" title="<?= htmlspecialchars(analytics_week_label(11 - $wi)) ?> · +<?= (int)$wv ?> XP"></span>
                <span class="chart-lbl"><?= $wi === 11 ? 'Kini' : 'M-' . (11 - $wi) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="skill-grid mb-3">
        <section class="card skill-card" aria-label="Antrean quest berikutnya">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-list-check"></i></span><div class="skill-id"><strong>Kerjakan berikutnya<?= count($queue) > 1 ? ' (' . count($queue) . ')' : '' ?></strong><small>rencana minggu ini</small></div></div>
            <?php if ($queue): ?>
            <div class="skill-meta" style="flex-direction:column;align-items:stretch;gap:4px;">
                <?php foreach ($queue as $qi => $q): ?><span><?= $qi + 1 ?>. <a href="quests.php"><?= htmlspecialchars(mb_strimwidth($q['title'], 0, 50, '...')) ?></a> · +<?= (int)$q['xp'] ?> XP</span><?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="skill-meta"><span class="text-success"><i class="fas fa-check me-1" aria-hidden="true"></i>Semua quest tuntas! Lihat <a href="digest.php">minggu depan</a>.</span></div>
            <?php endif; ?>
        </section>
        <section class="card skill-card" aria-label="Antrean review">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-rotate-right"></i></span><div class="skill-id"><strong><?= $due ?> review jatuh tempo</strong><small><?= $due > 0 ? 'sikat 2 menit' : 'inbox bersih' ?></small></div></div>
            <div class="skill-meta"><span><?= $due > 0 ? '<a href="review.php">Buka inbox review</a>' : 'Nanti muncul otomatis dari quest & catatan' ?></span></div>
        </section>
        <section class="card skill-card" aria-label="XP minggu ini">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-bolt"></i></span><div class="skill-id"><strong>+<?= $xp_now ?> XP</strong><small>minggu ini</small></div><span class="skill-lv"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?>%</span></div>
            <div class="skill-meta"><span>vs <?= $xp_prev ?> XP minggu lalu</span></div>
        </section>
        <section class="card skill-card" aria-label="Fokus minggu ini">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-clock"></i></span><div class="skill-id"><strong><?= $fs ?> sesi · <?= $fm ?> mnt</strong><small>fokus mendalam</small></div></div>
            <div class="skill-meta"><span><?= $qd ?> quest selesai</span><span><?= $notes ?> catatan &amp; tanya</span></div>
        </section>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="card p-4 h-100" aria-labelledby="ana-src-h">
                <div class="ana-head">
                    <div>
                        <h2 id="ana-src-h">Sumber XP 28 hari</h2>
                        <p>Dari mana XP-mu berasal</p>
                    </div>
                </div>
                <?php if ($by_reason): ?>
                <div class="ana-bars">
                    <?php foreach ($by_reason as $br): $rk = (string)($br['reason'] ?? 'other'); $lbl = $reason_labels[$rk] ?? ucfirst($rk); $sv = (int)($br['s'] ?? 0); $pw = max(4, (int)round($sv / $reason_max * 100)); ?>
                    <div class="ana-row"><span class="ana-lbl"><?= htmlspecialchars($lbl) ?></span><span class="ana-track"><span class="ana-fill" style="width:<?= $pw ?>%"></span></span><span class="ana-val">+<?= $sv ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-secondary small mb-0">Belum ada XP 28 hari terakhir. Mulai dari <a href="timer.php">sesi fokus</a> atau <a href="quests.php">quest</a>.</p>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card p-4 h-100" aria-labelledby="ana-skill-h">
                <div class="ana-head">
                    <div>
                        <h2 id="ana-skill-h">Sorotan skill</h2>
                        <p>Terkuat: <?= htmlspecialchars($strongest) ?> · Perhatian: <?= htmlspecialchars($attention) ?></p>
                    </div>
                    <a href="skills.php" class="small text-secondary text-decoration-none">Skill tree <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i></a>
                </div>
                <?php if ($skill_rows): ?>
                <div class="ana-bars">
                    <?php foreach (array_slice(array_reverse($skill_rows), 0, 5) as $sk): ?>
                    <div class="ana-row"><span class="ana-lbl"><?= htmlspecialchars($sk['name']) ?></span><span class="ana-track"><span class="ana-fill" style="width:<?= max(4, (int)$sk['pct']) ?>%"></span></span><span class="ana-val"><?= (int)$sk['done'] ?>/<?= (int)$sk['total'] ?></span></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-secondary small mb-0">Buka dengan 1 quest tuntas.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
