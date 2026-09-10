<?php
require_once 'config.php';
$conn = db_connect();
$uname = mb_substr(trim($_GET['u'] ?? ''), 0, 100);
$stmt = $conn->prepare("SELECT id, username, xp, streak, best_streak, public_profile, flair, avatar_frame, track, created_at FROM users WHERE username = ?");
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
$me = (int)($_SESSION['user_id'] ?? 0);
$level = calculate_level($user['xp']);
$rank = get_user_rank($level);
$userTrack = \App\Domain\Track\Tracks::normalize((string)($user['track'] ?? 'devops'));
$trackLabel = \App\Domain\Track\Tracks::all()[$userTrack]['name'] ?? 'DevOps';
$owned = user_badges($conn, $uid);
$defs = badge_defs();

// Ringkasan angka + 3 skill teratas (cache 5 menit, halaman publik)
$pub = \App\Cache\Store::remember("upub:{$uid}", 300, function () use ($conn, $uid, $userTrack) {
    $out = ['qt' => 0, 'qd' => 0, 'pomo' => 0, 'notes' => 0, 'top_skills' => []];
    try {
        $q = $conn->prepare("SELECT
            (SELECT COUNT(*) FROM quests WHERE (user_id IS NULL AND (track = ? OR track = 'all' OR track IS NULL OR track = '')) OR (user_id = ? AND (track = ? OR track IS NULL OR track = ''))) AS qt,
            (SELECT COUNT(*) FROM user_quests uq JOIN quests q2 ON q2.id = uq.quest_id WHERE uq.user_id = ? AND ((q2.user_id IS NULL AND (q2.track = ? OR q2.track = 'all' OR q2.track IS NULL OR q2.track = '')) OR (q2.user_id = ? AND (q2.track = ? OR q2.track IS NULL OR q2.track = '')))) AS qd,
            (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ?) AS pomo,
            (SELECT COUNT(*) FROM errors WHERE user_id = ?) AS notes");
        if ($q) {
            $q->bind_param("sisissisii", $userTrack, $uid, $userTrack, $uid, $userTrack, $uid, $userTrack, $uid, $uid, $uid);
            $q->execute();
            $r = $q->get_result()->fetch_assoc() ?: [];
            $q->close();
        } else {
            $q = $conn->prepare("SELECT (SELECT COUNT(*) FROM quests WHERE user_id IS NULL OR user_id = ?) AS qt, (SELECT COUNT(*) FROM user_quests uq JOIN quests q2 ON q2.id = uq.quest_id WHERE uq.user_id = ? AND (q2.user_id IS NULL OR q2.user_id = ?)) AS qd, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ?) AS pomo, (SELECT COUNT(*) FROM errors WHERE user_id = ?) AS notes");
            $q->bind_param("iiiii", $uid, $uid, $uid, $uid, $uid);
            $q->execute();
            $r = $q->get_result()->fetch_assoc() ?: [];
            $q->close();
        }
        $out['qt'] = (int)($r['qt'] ?? 0); $out['qd'] = (int)($r['qd'] ?? 0);
        $out['pomo'] = (int)($r['pomo'] ?? 0); $out['notes'] = (int)($r['notes'] ?? 0);
    } catch (Throwable $e) {}
    try {
        $agg = [];
        $s = $conn->prepare("SELECT q.week, q.xp_reward, (uq.quest_id IS NOT NULL) AS done FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE ((q.user_id IS NULL AND (q.track = ? OR q.track = 'all' OR q.track IS NULL OR q.track = '')) OR (q.user_id = ? AND (q.track = ? OR q.track IS NULL OR q.track = '')))");
        if (!$s) {
            $s = $conn->prepare("SELECT q.week, q.xp_reward, (uq.quest_id IS NOT NULL) AS done FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE (q.user_id IS NULL OR q.user_id = ?)");
            $s->bind_param("ii", $uid, $uid);
        } else $s->bind_param("isis", $uid, $userTrack, $uid, $userTrack);
        $s->execute();
        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $sk = skill_for_week((int)$row['week'], $userTrack);
            $agg[$sk] = ($agg[$sk] ?? 0) + (!empty($row['done']) ? (int)$row['xp_reward'] : 0);
        }
        $s->close();
        $s = $conn->prepare("SELECT category, COUNT(*) n FROM errors WHERE user_id = ? GROUP BY category");
        $s->bind_param("i", $uid); $s->execute();
        $tdefs = skill_defs($userTrack);
        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $cat = $row['category'] ?? 'General';
            $sk = isset($tdefs[$cat]) ? $cat : 'General';
            $agg[$sk] = ($agg[$sk] ?? 0) + (int)$row['n'] * 5;
        }
        $s->close();
        $s = $conn->prepare("SELECT topic, COUNT(*) n FROM questions WHERE user_id = ? GROUP BY topic");
        $s->bind_param("i", $uid); $s->execute();
        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $raw = normalize_skill($row['topic'] ?? '');
            if ($raw === '') continue;
            $sk = isset($tdefs[$raw]) ? $raw : 'General';
            $agg[$sk] = ($agg[$sk] ?? 0) + (int)$row['n'] * 3;
        }
        $s->close();
        arsort($agg);
        $out['top_skills'] = array_slice($agg, 0, 3, true);
    } catch (Throwable $e) {}
    return $out;
});
$qt = (int)($pub['qt'] ?? 0); $qd = (int)($pub['qd'] ?? 0);
$pomo = (int)($pub['pomo'] ?? 0); $notes = (int)($pub['notes'] ?? 0);
$top_skills = $pub['top_skills'] ?? [];
$qpct = $qt > 0 ? (int)round($qd / $qt * 100) : 0;
$cheers = [];
try {
    $s = $conn->prepare("SELECT c.id, c.body, c.created_at, c.from_id, u.username FROM cheers c JOIN users u ON u.id = c.from_id WHERE c.profile_id = ? ORDER BY c.id DESC LIMIT 8");
    $s->bind_param("i", $uid);
    $s->execute();
    $cheers = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
} catch (Throwable $e) {}
$incidents = [];
try {
    $s = $conn->prepare("SELECT c.title, c.skill, c.difficulty, MAX(a.score) best, COUNT(a.id) tries, MAX(a.created_at) last_at FROM incident_attempts a JOIN incident_challenges c ON c.id = a.challenge_id WHERE a.user_id = ? GROUP BY c.id, c.title, c.skill, c.difficulty ORDER BY best DESC");
    if ($s) { $s->bind_param("i", $uid); $s->execute(); $incidents = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close(); }
} catch (Throwable $e) {}
$certs = \App\Domain\Incident\Certificate::forUser($conn, $uid);
$rubric_avg = \App\Domain\Dkv\Rubric::portfolioAvg($conn, $uid);
$galeri = \App\Domain\Dkv\Karya::gallery($conn, $uid, 12);
$avgScore = $incidents ? (int)round(array_sum(array_column($incidents, 'best')) / count($incidents)) : 0;
$react_counts = []; $react_mine = [];
$react_emojis = \App\Domain\Social\Reactions::emojis();
if ($me > 0) {
    $react_counts = \App\Domain\Social\Reactions::counts($conn, 'profile', [$uid]);
    $react_mine = \App\Domain\Social\Reactions::mine($conn, $me, 'profile', [$uid]);
}
$conn->close();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$share_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/u.php?u=' . urlencode($user['username']);
$share_text = $user['username'] . ' · ' . $rank . ' Lv ' . $level . ' · ' . $user['xp'] . ' XP di Learn Tracker ' . $trackLabel;
$page_title = $user['username'] . ' · Learn Tracker';
require_once 'includes/header.php';
?>
<meta property="og:title" content="<?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($rank) ?> · Lv <?= $level ?>">
<meta property="og:description" content="<?= (int)$user['xp'] ?> XP · <?= $qd ?>/<?= $qt ?> quest · <?= $pomo ?> sesi fokus · <?= count($owned) ?> badge">
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Profil publik · Track <?= htmlspecialchars($trackLabel) ?> · Level <?= $level ?> · <?= htmlspecialchars($rank) ?></div>
        <div class="d-flex align-items-center gap-3 mb-2">
            <span class="avatar-circle avatar-xl frame-<?= htmlspecialchars($user['avatar_frame'] ?? 'default') ?>" aria-hidden="true"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
            <h1 class="page-title mb-0"><?= htmlspecialchars($user['username']) ?><?php if (!empty($user['flair'])): ?> <span class="flair-badge"><?= htmlspecialchars($user['flair']) ?></span><?php endif; ?></h1>
        </div>
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
        <?php if ($me > 0 && $me !== $uid): ?>
        <div id="reactCsrf" hidden><?= csrf_field() ?></div>
        <div class="react-bar" data-target="<?= $uid ?>">
            <?php foreach ($react_emojis as $ekey => $echar): $ecount = (int)($react_counts[$uid][$ekey] ?? 0); $eon = in_array($ekey, $react_mine[$uid] ?? [], true); ?>
            <button type="button" class="react-btn<?= $eon ? ' on' : '' ?>" data-emoji="<?= $ekey ?>" aria-label="Reaksi <?= $ekey ?>" aria-pressed="<?= $eon ? 'true' : 'false' ?>"><?= $echar ?><span><?= $ecount ?></span></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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

    <?php if ($incidents): ?>
    <section class="card p-4 mb-3" aria-label="Verified challenges">
        <h2 class="h5 fw-bold mb-1">Skill Passport · <?= count($incidents) ?> verified · rata-rata <?= $avgScore ?>/100</h2>
        <p class="text-secondary small mb-3">Bukti skill incident · konsisten <?= (int)$user['best_streak'] ?> hari · sejak <?= date('M Y', strtotime($user['created_at'])) ?>.</p>
        <div class="d-flex flex-column gap-2">
        <?php foreach ($incidents as $in): ?>
            <div class="list-row"><div class="list-main"><p class="list-title"><?= htmlspecialchars($in['title']) ?></p><p class="list-meta"><?= htmlspecialchars($in['skill']) ?> · <?= htmlspecialchars($in['difficulty'] ?? '') ?> · score <?= (int)$in['best'] ?>/100 · <?= (int)$in['tries'] ?>x coba<?= !empty($in['last_at']) ? ' · ' . date('M Y', strtotime($in['last_at'])) : '' ?></p></div><span class="quest-badge-xp">verified</span></div>
        <?php endforeach; ?>
        </div>
        <?php if ($certs): ?>
        <h3 class="h6 fw-bold mt-3 mb-2">Sertifikat (<?= count($certs) ?>)</h3>
        <div class="d-flex flex-column gap-2">
        <?php foreach ($certs as $ct): ?>
            <div class="list-row"><div class="list-main"><p class="list-title"><?= htmlspecialchars($ct['title']) ?> · <?= (int)$ct['score'] ?>/100</p><p class="list-meta"><?= htmlspecialchars($ct['code']) ?> · <?= date('d M Y', strtotime($ct['issued_at'])) ?></p></div><span class="quest-badge-xp">verified</span></div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if (!empty($rubric_avg['quests'])): ?>
    <section class="card p-4 mb-3" aria-label="Nilai karya">
        <h2 class="h5 fw-bold mb-1">Nilai karya · <?= htmlspecialchars((string)$rubric_avg['avg']) ?>/5</h2>
        <p class="text-secondary small mb-0">Rubrik DKV (konsep, tipografi, warna, layout, presentasi) · <?= (int)$rubric_avg['quests'] ?> karya dinilai.</p>
    </section>
    <?php endif; ?>
    <?php if ($galeri): ?>
    <section class="card p-4 mb-3" aria-label="Galeri karya">
        <h2 class="h5 fw-bold mb-1">Galeri karya (<?= count($galeri) ?>)</h2>
        <p class="text-secondary small mb-3">Bukti visual dari quest yang dikerjakan.</p>
        <div class="d-flex flex-wrap gap-2">
        <?php foreach ($galeri as $g): ?>
            <a href="karya.php?id=<?= (int)$g['id'] ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($g['title']) ?>"><img src="karya.php?id=<?= (int)$g['id'] ?>" alt="<?= htmlspecialchars($g['title']) ?>" loading="lazy" style="width:120px;height:90px;object-fit:cover;border-radius:8px"></a>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <section class="card p-4">
        <h2 class="h5 fw-bold mb-3">Badge (<?= count($owned) ?>)</h2>
        <div class="badge-grid">
            <?php foreach ($owned as $slug => $b): $d = $defs[$slug] ?? ['name' => $slug, 'icon' => 'fa-medal']; $btext = badge_share_text($user['username'], $d['name']); ?>
            <div class="badge-item unlocked"><i class="fas <?= htmlspecialchars($d['icon']) ?>"></i><strong><?= htmlspecialchars($d['name']) ?></strong><span><?= date('M Y', strtotime($b['unlocked_at'])) ?></span><span class="cheer-share"><a target="_blank" rel="noopener" href="https://wa.me/?text=<?= urlencode($btext . ' ' . $share_url) ?>" aria-label="Bagikan badge <?= htmlspecialchars($d['name']) ?> ke WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a><a target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= urlencode($btext) ?>&url=<?= urlencode($share_url) ?>" aria-label="Bagikan badge <?= htmlspecialchars($d['name']) ?> ke X"><i class="fab fa-x-twitter" aria-hidden="true"></i></a></span></div>
            <?php endforeach; ?>
            <?php if (!$owned): ?><p class="small text-muted mb-0">Belum ada badge terbuka.</p><?php endif; ?>
        </div>
    </section>

    <?php if ($cheers): ?>
    <section class="card p-4 mt-4" aria-label="Dukungan">
        <h2 class="h5 fw-bold mb-1">Dukungan (<?= count($cheers) ?>)</h2>
        <p class="text-secondary small mb-3">Arsip dukungan untuk profil ini.</p>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($cheers as $ch): ?>
            <div class="cheer-row">
                <span class="avatar-circle avatar-sm" aria-hidden="true"><?= strtoupper(substr($ch['username'], 0, 1)) ?></span>
                <div class="list-main"><p class="list-title"><?= htmlspecialchars($ch['username']) ?></p><p class="list-meta"><?= htmlspecialchars($ch['body']) ?> · <?= date('d M', strtotime($ch['created_at'])) ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
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
