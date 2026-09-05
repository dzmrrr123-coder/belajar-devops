<?php
require_once 'config.php';
$conn = db_connect();
$uname = mb_substr(trim($_GET['u'] ?? ''), 0, 100);
$stmt = $conn->prepare("SELECT id, username, xp, streak, public_profile, created_at FROM users WHERE username = ?");
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
$qd = 0; $qt = 0; $pomo = 0; $notes = 0;
$q = $conn->prepare("SELECT COUNT(*) c FROM quests WHERE user_id IS NULL OR user_id = ?");
$q->bind_param("i", $uid); $q->execute(); $qt = (int)($q->get_result()->fetch_assoc()['c'] ?? 0); $q->close();
$q = $conn->prepare("SELECT COUNT(*) c FROM user_quests uq JOIN quests q2 ON q2.id=uq.quest_id WHERE uq.user_id=? AND (q2.user_id IS NULL OR q2.user_id=?)");
$q->bind_param("ii", $uid, $uid); $q->execute(); $qd = (int)($q->get_result()->fetch_assoc()['c'] ?? 0); $q->close();
$q = $conn->prepare("SELECT COUNT(*) c FROM pomodoro_sessions WHERE user_id=?");
$q->bind_param("i", $uid); $q->execute(); $pomo = (int)($q->get_result()->fetch_assoc()['c'] ?? 0); $q->close();
$q = $conn->prepare("SELECT COUNT(*) c FROM errors WHERE user_id=?");
$q->bind_param("i", $uid); $q->execute(); $notes = (int)($q->get_result()->fetch_assoc()['c'] ?? 0); $q->close();
$conn->close();
$page_title = $user['username'] . ' · Learn Tracker';
require_once 'includes/header.php';
?>
<meta property="og:title" content="<?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($rank) ?> · Lv <?= $level ?>">
<meta property="og:description" content="<?= (int)$user['xp'] ?> XP · <?= $qd ?>/<?= $qt ?> quest · <?= $pomo ?> sesi fokus · <?= count($owned) ?> badge">
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Profil publik · Level <?= $level ?> · <?= htmlspecialchars($rank) ?></div>
        <h1 class="page-title"><?= htmlspecialchars($user['username']) ?></h1>
        <p class="page-desc"><?= (int)$user['xp'] ?> XP · <?= $qd ?>/<?= $qt ?> quest · <?= $pomo ?> fokus · <?= $notes ?> catatan · <?= count($owned) ?> badge</p>
        <div class="page-actions"><a href="register.php" class="btn btn-cyber btn-sm">Buat trackermu</a></div>
    </div>
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
<?php require_once 'includes/footer.php'; ?>
