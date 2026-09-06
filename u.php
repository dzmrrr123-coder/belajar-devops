<?php
require_once 'config.php';
$conn = db_connect();
$uname = mb_substr(trim($_GET['u'] ?? ''), 0, 100);
$stmt = $conn->prepare("SELECT id, username, xp, streak, best_streak, public_profile, created_at FROM users WHERE username = ?");
$stmt->bind_param("s", $uname);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user || empty($user['public_profile'])) {
    http_response_code(404);
    $page_title = 'Tidak ditemukan';
    require_once 'includes/header.php';
    echo '<main class="container py-5"><div class="empty-state card p-5"><h1 class="h5 fw-bold">Profil privat atau tidak ada.</h1><p class="text-secondary small mb-0">Minta pemilik mengaktifkan “Profil publik” di halaman Profil.</p></div></main>';
    require_once 'includes/footer.php';
    exit();
}
$uid = (int)$user['id'];
$level = calculate_level($user['xp']);
$rank = get_user_rank($level);
$owned = user_badges($conn, $uid);
$defs = badge_defs();

// Ringkasan angka (1 roundtrip)
$qd = 0; $qt = 0; $pomo = 0; $notes = 0;
try {
    $q = $conn->prepare("SELECT (SELECT COUNT(*) FROM quests WHERE user_id IS NULL OR user_id = ?) AS qt, (SELECT COUNT(*) FROM user_quests uq JOIN quests q2 ON q2.id = uq.quest_id WHERE uq.user_id = ? AND (q2.user_id IS NULL OR q2.user_id = ?)) AS qd, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ?) AS pomo, (SELECT COUNT(*) FROM errors WHERE user_id = ?) AS notes");
    $q->bind_param("iiiii", $uid, $uid, $uid, $uid, $uid);
    $q->execute();
    $r = $q->get_result()->fetch_assoc() ?: [];
    $q->close();
    $qt = (int)($r['qt'] ?? 0); $qd = (int)($r['qd'] ?? 0);
    $pomo = (int)($r['pomo'] ?? 0); $notes = (int)($r['notes'] ?? 0);
} catch (Throwable $e) {}
$qpct = $qt > 0 ? (int)round($qd / $qt * 100) : 0;

// 3 skill teratas (3 roundtrip ringan, halaman publik jarang dibuka)
$top_skills = [];
try {
    $agg = [];
    $s = $conn->prepare("SELECT q.week, q.xp_reward, (uq.quest_id IS NOT NULL) AS done FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE (q.user_id IS NULL OR q.user_id = ?)");
    $s->bind_param("ii", $uid, $uid); $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $sk = skill_for_week((int)$row['week']);
        $agg[$sk] = ($agg[$sk] ?? 0) + (!empty($row['done']) ? (int)$row['xp_reward'] : 0);
    }
    $s->close();
    $s = $conn->prepare("SELECT category, COUNT(*) n FROM errors WHERE user_id = ? GROUP BY category");
    $s->bind_param("i", $uid); $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $cat = $row['category'] ?? 'General';
        $sk = isset(skill_defs()[$cat]) ? $cat : 'General';
        $agg[$sk] = ($agg[$sk] ?? 0) + (int)$row['n'] * 5;
    }
    $s->close();
    $s = $conn->prepare("SELECT topic, COUNT(*) n FROM questions WHERE user_id = ? GROUP BY topic");
    $s->bind_param("i", $uid); $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $sk = normalize_skill($row['topic'] ?? '');
        if ($sk === '') continue;
        $agg[$sk] = ($agg[$sk] ?? 0) + (int)$row['n'] * 3;
    }
    $s->close();
    arsort($agg);
    $top_skills = array_slice($agg, 0, 3, true);
} catch (Throwable $e) {}
$conn->close();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$share_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/u.php?u=' . urlencode($user['username']);
$share_text = $user['username'] . ' · ' . $rank . ' Lv ' . $level . ' · ' . $user['xp'] . ' XP di Learn Tracker DevOps';
$page_title = $user['username'] . ' · Learn Tracker';
require_once 'includes/header.php';
?>
<meta property="og:title" content="<?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($rank) ?> · Lv <?= $level ?>">
<meta property="og:description" content="<?= (int)$user['xp'] ?> XP · <?= $qd ?>/<?= $qt ?> quest · <?= $pomo ?> sesi fokus · <?= count($owned) ?> badge">
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Profil publik · Level <?= $level ?> · <?= htmlspecialchars($rank) ?></div>
        <h1 class="page-title"><?= htmlspecialchars($user['username']) ?></h1>
        <p class="page-desc"><?= (int)$user['xp'] ?> XP · <?= (int)$user['streak'] ?> streak (terbaik <?= (int)($user['best_streak'] ?? 0) ?>) · sejak <?= date('M Y', strtotime($user['created_at'])) ?></p>
        <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $qpct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres quest"><div class="xp-progress-fill" style="width: <?= $qpct ?>%;"></div></div>
        <div class="strip-meta"><span><?= $qd ?>/<?= $qt ?> quest (<?= $qpct ?>%)</span><span><?= $pomo ?> fokus · <?= $notes ?> catatan · <?= count($owned) ?> badge</span></div>
        <div class="share-row">
            <button type="button" class="btn btn-cyber-outline btn-sm" id="shareCopy"><i class="fas fa-link me-1" aria-hidden="true"></i>Salin link</button>
            <a class="btn btn-cyber-outline btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= urlencode($share_text . ' ' . $share_url) ?>" aria-label="Bagikan ke WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a>
            <a class="btn btn-cyber-outline btn-sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= urlencode($share_text) ?>&url=<?= urlencode($share_url) ?>" aria-label="Bagikan ke X"><i class="fab fa-x-twitter" aria-hidden="true"></i></a>
            <a class="btn btn-cyber-outline btn-sm" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($share_url) ?>" aria-label="Bagikan ke LinkedIn"><i class="fab fa-linkedin" aria-hidden="true"></i></a>
            <a href="register.php" class="btn btn-cyber btn-sm">Buat trackermu</a>
        </div>
    </div>

    <?php if ($top_skills): ?>
    <section aria-label="Skill teratas">
        <div class="quest-section-head"><div><h2>Skill teratas</h2><p>Terbukti dari quest, catatan, dan pertanyaan.</p></div></div>
        <div class="skill-grid mb-4">
            <?php $sd = skill_defs(); foreach ($top_skills as $name => $pts): $lv = calculate_level($pts); ?>
            <div class="card skill-card">
                <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="<?= htmlspecialchars($sd[$name]['icon'] ?? 'fas fa-layer-group') ?>"></i></span><div class="skill-id"><strong><?= htmlspecialchars($name) ?></strong><small><?= $pts ?> poin</small></div><span class="skill-lv">Lv <?= $lv ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card p-4">
        <h2 class="h5 fw-bold mb-3">Badge (<?= count($owned) ?>)</h2>
        <div class="badge-grid">
            <?php foreach ($owned as $slug => $b): $d = $defs[$slug] ?? ['name' => $slug, 'icon' => 'fa-medal']; ?>
            <div class="badge-item unlocked"><i class="fas <?= htmlspecialchars($d['icon']) ?>"></i><strong><?= htmlspecialchars($d['name']) ?></strong><span><?= date('M Y', strtotime($b['unlocked_at'])) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$owned): ?><p class="small text-muted mb-0">Belum ada badge terbuka.</p><?php endif; ?>
        </div>
    </section>
</main>
<script>
document.getElementById('shareCopy')?.addEventListener('click', async function() {
    const url = <?= json_encode($share_url) ?>;
    try {
        await navigator.clipboard.writeText(url);
        showToast('Link profil disalin.', 'success');
    } catch (e) {
        prompt('Salin link profil:', url);
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
