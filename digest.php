<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Semua angka ringkasan dalam 1 roundtrip
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

// Spotlight skill dari quest + antrean 3 quest berikut (1 roundtrip)
$strongest = '—'; $attention = '—'; $queue = [];
try {
    $s = $conn->prepare("SELECT q.id, q.week, q.title, q.xp_reward, (uq.quest_id IS NOT NULL) AS done FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE (q.user_id IS NULL OR q.user_id = ?) ORDER BY q.week ASC, q.id ASC");
    $s->bind_param("ii", $user_id, $user_id);
    $s->execute();
    $per = [];
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $sk = skill_for_week((int)$r['week']);
        $per[$sk] = $per[$sk] ?? ['done' => 0, 'total' => 0];
        $per[$sk]['total']++;
        if (!empty($r['done'])) {
            $per[$sk]['done']++;
        } elseif (count($queue) < 3) {
            $queue[] = ['title' => (string)$r['title'], 'xp' => (int)$r['xp_reward']];
        }
    }
    $s->close();
    $best = -1; $most_left = -1;
    foreach ($per as $name => $p) {
        if ($p['done'] > $best) { $best = $p['done']; $strongest = $name; }
        if (($p['total'] - $p['done']) > $most_left) { $most_left = $p['total'] - $p['done']; $attention = $name; }
    }
    if ($best <= 0) $strongest = '—';
    if ($most_left <= 0) $attention = '—';
} catch (Throwable $e) {}
$conn->close();

$delta = $xp_prev > 0 ? (int)round(($xp_now - $xp_prev) / $xp_prev * 100) : ($xp_now > 0 ? 100 : 0);
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
$page_title = 'Ringkasan Mingguan';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">7 hari terakhir · vs minggu lalu</div>
        <h1 class="page-title">Ringkasan mingguan</h1>
        <p class="page-desc">Ritme belajarmu minggu ini vs minggu lalu — plus satu aksi berikutnya.</p>
    </div>

    <section class="card p-4 mb-4" aria-label="Vonis minggu ini" style="border-left:4px solid <?= $delta_bad ? 'var(--danger)' : 'var(--primary)' ?>;">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div style="font-size:2rem;font-weight:800;line-height:1;" class="<?= $delta_bad ? 'text-danger' : 'text-emerald' ?>"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?>%</div>
            <div class="flex-grow-1" style="min-width:200px;">
                <strong><?= htmlspecialchars($verdict) ?></strong>
                <div class="text-secondary small">+<?= $xp_now ?> XP · <?= $fs ?> sesi fokus · <?= $qd ?> quest · <?= $notes ?> catatan minggu ini</div>
            </div>
            <a href="<?= $cta_href ?>" class="btn btn-cyber"><i class="fas <?= $cta_icon ?> me-1" aria-hidden="true"></i><?= htmlspecialchars($cta_label) ?></a>
        </div>
        <div class="text-secondary small mt-1"><?= htmlspecialchars($cta_sub) ?></div>
    </section>

    <div class="skill-grid">
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
        <section class="card skill-card" aria-label="Sorotan skill">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span><div class="skill-id"><strong>Terkuat: <?= htmlspecialchars($strongest) ?></strong><small><?= $strongest === '—' ? 'buka dengan 1 quest tuntas' : 'dari quest tuntas' ?></small></div></div>
            <div class="skill-meta"><span>Perlu perhatian: <strong><?= htmlspecialchars($attention) ?></strong></span><span><a href="skills.php">Buka skill tree</a></span></div>
        </section>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
