<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$defs = skill_defs();
$skills = [];
foreach ($defs as $name => $d) {
    $skills[$name] = ['quest_done' => 0, 'quest_total' => 0, 'quest_xp' => 0, 'notes' => 0, 'asks' => 0] + $d;
}

// Quest selesai + total per skill (1 roundtrip)
try {
    $s = $conn->prepare("SELECT q.week, q.xp_reward, (uq.quest_id IS NOT NULL) AS done FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE (q.user_id IS NULL OR q.user_id = ?)");
    $s->bind_param("ii", $user_id, $user_id);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $sk = skill_for_week((int)$r['week']);
        $skills[$sk]['quest_total']++;
        if (!empty($r['done'])) {
            $skills[$sk]['quest_done']++;
            $skills[$sk]['quest_xp'] += (int)$r['xp_reward'];
        }
    }
    $s->close();
} catch (Throwable $e) {}

// Catatan per kategori (1 roundtrip)
try {
    $s = $conn->prepare("SELECT category, COUNT(*) n FROM errors WHERE user_id = ? GROUP BY category");
    $s->bind_param("i", $user_id);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $cat = $r['category'] ?? 'General';
        $sk = isset($defs[$cat]) ? $cat : 'General';
        $skills[$sk]['notes'] += (int)$r['n'];
    }
    $s->close();
} catch (Throwable $e) {}

// Pertanyaan per topik ternormalisasi (1 roundtrip)
try {
    $s = $conn->prepare("SELECT topic, COUNT(*) n FROM questions WHERE user_id = ? GROUP BY topic");
    $s->bind_param("i", $user_id);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $sk = normalize_skill($r['topic'] ?? '');
        if ($sk === '' || !isset($skills[$sk])) continue;
        $skills[$sk]['asks'] += (int)$r['n'];
    }
    $s->close();
} catch (Throwable $e) {}

foreach ($skills as $name => &$sk) {
    $sk['points'] = $sk['quest_xp'] + $sk['notes'] * 5 + $sk['asks'] * 3;
    $sk['level'] = calculate_level($sk['points']);
    $sk['pct'] = level_progress_percent($sk['points']);
    $sk['need'] = max(0, xp_to_next_level($sk['points']) - $sk['points']);
}
unset($sk);
uasort($skills, fn($a, $b) => $b['points'] <=> $a['points']);
$names = array_keys($skills);
$top = $names[0] ?? '—';
$total_points = array_sum(array_column($skills, 'points'));
$active = count(array_filter($skills, fn($s) => $s['points'] > 0));
$next_up = null;
foreach ($skills as $name => $sk) {
    if ($sk['points'] <= 0) continue;
    if ($next_up === null || $sk['pct'] > $skills[$next_up]['pct']) $next_up = $name;
}
$feat = $skills[$top];
unset($skills[$top]);
$conn->close();

$page_title = 'Skill Tree';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">8 skill · <?= $total_points ?> poin · <?= $active ?>/8 aktif</div>
        <h1 class="page-title">Skill tree</h1>
        <p class="page-desc">Poin skill = XP quest + 5 per catatan + 3 per pertanyaan.<?php if ($next_up !== null): ?> Dekat naik level: <strong><?= htmlspecialchars($next_up) ?></strong> <?= $skills[$next_up]['pct'] ?>% — <?= $skills[$next_up]['need'] ?> poin lagi.<?php endif; ?></p>
    </div>

    <section class="card skill-hero" aria-label="Skill terkuat <?= htmlspecialchars($top) ?>">
        <span class="skill-rank rank-1" aria-hidden="true">#1</span>
        <span class="skill-icon skill-icon-lg" aria-hidden="true"><i class="<?= htmlspecialchars($feat['icon']) ?>"></i></span>
        <div class="skill-hero-main">
            <div class="skill-hero-id"><strong><?= htmlspecialchars($top) ?></strong><span class="skill-lv">Lv <?= $feat['level'] ?></span></div>
            <small><?= htmlspecialchars($feat['desc']) ?></small>
            <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $feat['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres <?= htmlspecialchars($top) ?> menuju level berikutnya"><div class="xp-progress-fill" style="width: <?= $feat['pct'] ?>%;"></div></div>
            <div class="skill-meta"><span><strong><?= $feat['points'] ?></strong> poin</span><span><?= $feat['quest_done'] ?>/<?= $feat['quest_total'] ?> quest</span><span><?= $feat['notes'] ?> catatan</span><span><?= $feat['asks'] ?> tanya</span></div>
        </div>
    </section>

    <div class="skill-grid">
        <?php $rank = 1; foreach ($skills as $name => $sk): $rank++; $touched = $sk['points'] > 0; ?>
        <section class="card skill-card <?= $touched ? '' : 'untouched' ?>" aria-label="Skill <?= htmlspecialchars($name) ?>">
            <div class="skill-top">
                <span class="skill-rank" aria-hidden="true">#<?= $rank ?></span>
                <span class="skill-icon" aria-hidden="true"><i class="<?= htmlspecialchars($sk['icon']) ?>"></i></span>
                <div class="skill-id">
                    <strong><?= htmlspecialchars($name) ?></strong>
                    <small><?= $touched ? htmlspecialchars($sk['desc']) : 'Belum mulai' ?></small>
                </div>
                <span class="skill-lv">Lv <?= $sk['level'] ?></span>
            </div>
            <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $sk['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres <?= htmlspecialchars($name) ?> menuju level berikutnya"><div class="xp-progress-fill" style="width: <?= $sk['pct'] ?>%;"></div></div>
            <div class="skill-meta"><span><strong><?= $sk['points'] ?></strong> poin</span><span><?= $sk['quest_done'] ?>/<?= $sk['quest_total'] ?> quest</span><span><?= $sk['notes'] ?> catatan</span><span><?= $sk['asks'] ?> tanya</span></div>
        </section>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
