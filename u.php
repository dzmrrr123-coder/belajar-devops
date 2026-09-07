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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me > 0) {
    verify_csrf();
    $caction = $_POST['action'] ?? '';
    if ($caction === 'cheer' && $me !== $uid) {
        if (rate_limit_hit('cheer_post', 10, 60)) {
            set_flash('warning', 'Terlalu cepat. Tunggu sebentar.');
            redirect('u.php?u=' . urlencode($user['username']));
        }
        $body = cheer_clean($_POST['body'] ?? '');
        if ($body === '') {
            set_flash('warning', 'Tulis dukungan minimal 2 karakter.');
        } else {
            $rl = $conn->prepare("SELECT COUNT(*) c FROM cheers WHERE from_id = ? AND created_at >= CURDATE()");
            $rl->bind_param("i", $me);
            $rl->execute();
            $today_n = (int)($rl->get_result()->fetch_assoc()['c'] ?? 0);
            $rl->close();
            if ($today_n >= 5) {
                set_flash('warning', 'Batas 5 dukungan per hari tercapai.');
            } else {
                $ins = $conn->prepare("INSERT INTO cheers (profile_id, from_id, body) VALUES (?, ?, ?)");
                $ins->bind_param("iis", $uid, $me, $body);
                $ins->execute();
                $ins->close();
                set_flash('success', 'Dukungan terkirim.');
            }
        }
        redirect('u.php?u=' . urlencode($user['username']));
    }
    if ($caction === 'cheer_delete') {
        $cheer_id = (int)($_POST['cheer_id'] ?? 0);
        $chk = $conn->prepare("SELECT id, profile_id, from_id FROM cheers WHERE id = ?");
        $chk->bind_param("i", $cheer_id);
        $chk->execute();
        $crow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($crow && ((int)$crow['from_id'] === $me || (int)$crow['profile_id'] === $me)) {
            $del = $conn->prepare("DELETE FROM cheers WHERE id = ?");
            $del->bind_param("i", $cheer_id);
            $del->execute();
            $del->close();
            set_flash('info', 'Dukungan dihapus.');
        }
        redirect('u.php?u=' . urlencode($user['username']));
    }
}
$level = calculate_level($user['xp']);
$rank = get_user_rank($level);
$userTrack = \App\Domain\Track\Tracks::normalize((string)($user['track'] ?? 'devops'));
$trackLabel = \App\Domain\Track\Tracks::all()[$userTrack]['name'] ?? 'DevOps';
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
    $top_skills = array_slice($agg, 0, 3, true);
} catch (Throwable $e) {}
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
            <?php if ($me > 0 && $me !== $uid): ?>
            <a href="duels.php?vs=<?= urlencode($user['username']) ?>" class="btn btn-cyber btn-sm"><i class="fas fa-hand-fist me-1" aria-hidden="true"></i>Tantang duel</a>
            <?php endif; ?>
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
            <div class="list-row"><div class="list-main"><p class="list-title"><?= htmlspecialchars($ct['title']) ?> · <?= (int)$ct['score'] ?>/100</p><p class="list-meta"><?= htmlspecialchars($ct['code']) ?> · <?= date('d M Y', strtotime($ct['issued_at'])) ?></p></div><a class="btn btn-cyber-outline btn-sm" href="certificate.php?code=<?= urlencode($ct['code']) ?>">Verifikasi</a></div>
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
    <section class="card p-4">
        <h2 class="h5 fw-bold mb-3">Badge (<?= count($owned) ?>)</h2>
        <div class="badge-grid">
            <?php foreach ($owned as $slug => $b): $d = $defs[$slug] ?? ['name' => $slug, 'icon' => 'fa-medal']; $btext = badge_share_text($user['username'], $d['name']); ?>
            <div class="badge-item unlocked"><i class="fas <?= htmlspecialchars($d['icon']) ?>"></i><strong><?= htmlspecialchars($d['name']) ?></strong><span><?= date('M Y', strtotime($b['unlocked_at'])) ?></span><span class="cheer-share"><a target="_blank" rel="noopener" href="https://wa.me/?text=<?= urlencode($btext . ' ' . $share_url) ?>" aria-label="Bagikan badge <?= htmlspecialchars($d['name']) ?> ke WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a><a target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= urlencode($btext) ?>&url=<?= urlencode($share_url) ?>" aria-label="Bagikan badge <?= htmlspecialchars($d['name']) ?> ke X"><i class="fab fa-x-twitter" aria-hidden="true"></i></a></span></div>
            <?php endforeach; ?>
            <?php if (!$owned): ?><p class="small text-muted mb-0">Belum ada badge terbuka.</p><?php endif; ?>
        </div>
    </section>

    <section class="card p-4 mt-4" aria-label="Dukungan">
        <h2 class="h5 fw-bold mb-1">Dukungan (<?= count($cheers) ?>)</h2>
        <p class="text-secondary small mb-3">Semangati pemilik profil. Maks 5 per hari.</p>
        <?php if ($cheers): ?>
        <div class="d-flex flex-column gap-2 mb-3">
            <?php foreach ($cheers as $ch): $can_del = ($me > 0 && ((int)$ch['from_id'] === $me || $me === $uid)); ?>
            <div class="cheer-row">
                <span class="avatar-circle avatar-sm" aria-hidden="true"><?= strtoupper(substr($ch['username'], 0, 1)) ?></span>
                <div class="list-main"><p class="list-title"><?= htmlspecialchars($ch['username']) ?></p><p class="list-meta"><?= htmlspecialchars($ch['body']) ?> · <?= date('d M', strtotime($ch['created_at'])) ?></p></div>
                <?php if ($can_del): ?>
                <form method="POST" action="u.php?u=<?= urlencode($user['username']) ?>" class="m-0" onsubmit="return confirm('Hapus dukungan ini?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cheer_delete">
                    <input type="hidden" name="cheer_id" value="<?= (int)$ch['id'] ?>">
                    <button type="submit" class="btn btn-cyber-danger btn-sm py-1" aria-label="Hapus dukungan"><i class="fas fa-trash" aria-hidden="true"></i></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-secondary small mb-3">Belum ada dukungan. Jadilah yang pertama.</p>
        <?php endif; ?>
        <?php if ($me > 0 && $me !== $uid): ?>
        <form method="POST" action="u.php?u=<?= urlencode($user['username']) ?>" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cheer">
            <input name="body" class="form-control form-control-sm" maxlength="140" placeholder="Semangat, lanjutkan!" aria-label="Tulis dukungan" required>
            <button type="submit" class="btn btn-cyber btn-sm flex-shrink-0">Kirim</button>
        </form>
        <?php elseif ($me === 0): ?>
        <a href="login.php" class="btn btn-cyber-outline btn-sm">Masuk untuk memberi dukungan</a>
        <?php endif; ?>
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
