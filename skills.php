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
}
unset($sk);
uasort($skills, fn($a, $b) => $b['points'] <=> $a['points']);
$top = array_key_first($skills);
$total_points = array_sum(array_column($skills, 'points'));
$conn->close();

$page_title = 'Skill Tree';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">8 skill · <?= $total_points ?> poin · terkuat: <?= htmlspecialchars($top) ?></div>
        <h1 class="page-title">Skill tree</h1>
        <p class="page-desc">Poin skill = XP quest + 5 per catatan + 3 per pertanyaan. Kerjakan quest, catat error, banyak tanya.</p>
    </div>

    <div class="skill-grid">
        <?php foreach ($skills as $name => $sk): ?>
        <section class="card skill-card" aria-label="Skill <?= htmlspecialchars($name) ?>">
            <div class="skill-top">
                <span class="skill-icon" aria-hidden="true"><i class="<?= htmlspecialchars($sk['icon']) ?>"></i></span>
                <div class="skill-id">
                    <strong><?= htmlspecialchars($name) ?></strong>
                    <small><?= htmlspecialchars($sk['desc']) ?></small>
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
