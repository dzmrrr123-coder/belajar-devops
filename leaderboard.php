<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ch_action = $_POST['challenge_action'] ?? '';
    if ($ch_action === 'join' || $ch_action === 'leave') {
        $ch = ensure_weekly_challenge($conn);
        if ($ch) {
            $cid = (int)$ch['id'];
            if ($ch_action === 'join') {
                $j = $conn->prepare("INSERT IGNORE INTO challenge_joins (challenge_id, user_id) VALUES (?, ?)");
                $j->bind_param("ii", $cid, $user_id);
                $j->execute();
                $j->close();
                set_flash('success', 'Ikut tantangan: ' . $ch['title'] . '.');
            } else {
                $j = $conn->prepare("DELETE FROM challenge_joins WHERE challenge_id = ? AND user_id = ?");
                $j->bind_param("ii", $cid, $user_id);
                $j->execute();
                $j->close();
                set_flash('info', 'Keluar dari tantangan minggu ini.');
            }
        }
        redirect('leaderboard.php?scope=' . urlencode($_POST['scope'] ?? 'total'));
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$off = ($page - 1) * $per;
$total = 0;
try {
    $t = $conn->query("SELECT COUNT(*) c FROM users WHERE show_on_board = 1");
    if ($t) { $total = (int)($t->fetch_assoc()['c'] ?? 0); $t->free(); }
} catch (Throwable $e) {}
$scope = ($_GET['scope'] ?? 'total') === 'week' ? 'week' : 'total';
$rows = [];
try {
    if ($scope === 'week') {
        $s = $conn->prepare("SELECT u.username, u.xp, u.streak, u.public_profile, u.flair, u.avatar_frame, GREATEST(0, COALESCE(w.wxp, 0)) wxp, COALESCE(qc.qd, 0) qd FROM users u LEFT JOIN (SELECT user_id, SUM(amount) wxp FROM xp_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY user_id) w ON w.user_id = u.id LEFT JOIN (SELECT user_id, COUNT(*) qd FROM user_quests GROUP BY user_id) qc ON qc.user_id = u.id WHERE u.show_on_board = 1 ORDER BY wxp DESC, u.streak DESC LIMIT ? OFFSET ?");
    } else {
        $s = $conn->prepare("SELECT u.username, u.xp, u.streak, u.public_profile, u.flair, u.avatar_frame, u.xp AS wxp, COALESCE(qc.qd, 0) qd FROM users u LEFT JOIN (SELECT user_id, COUNT(*) qd FROM user_quests GROUP BY user_id) qc ON qc.user_id = u.id WHERE u.show_on_board = 1 ORDER BY u.xp DESC, u.streak DESC LIMIT ? OFFSET ?");
    }
    $s->bind_param("ii", $per, $off);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
} catch (Throwable $e) {}
$my_rank = null;
$on_board = false;
try {
    if ($scope === 'week') {
        $c = $conn->prepare("SELECT show_on_board, (SELECT COUNT(*) FROM users WHERE show_on_board = 1 AND GREATEST(0, COALESCE((SELECT SUM(amount) FROM xp_events WHERE user_id = users.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0)) > (SELECT GREATEST(0, COALESCE(SUM(amount),0)) FROM xp_events WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))) + 1 AS r FROM users WHERE id = ?");
    } else {
        $c = $conn->prepare("SELECT show_on_board, (SELECT COUNT(*) FROM users WHERE show_on_board = 1 AND xp > (SELECT xp FROM users WHERE id = ?)) + 1 AS r FROM users WHERE id = ?");
    }
    $c->bind_param("ii", $user_id, $user_id); $c->execute();
    $me = $c->get_result()->fetch_assoc() ?: [];
    $c->close();
    $on_board = !empty($me['show_on_board']);
    if ($on_board) $my_rank = (int)($me['r'] ?? 0);
} catch (Throwable $e) {}
$challenge = ensure_weekly_challenge($conn);
$racers = [];
$my_race = null;
$joined = false;
if ($challenge) {
    $cid = (int)$challenge['id'];
    try {
        $j = $conn->prepare("SELECT 1 FROM challenge_joins WHERE challenge_id = ? AND user_id = ?");
        $j->bind_param("ii", $cid, $user_id);
        $j->execute();
        $joined = (bool)$j->get_result()->fetch_assoc();
        $j->close();
        $r = $conn->prepare("SELECT u.id, u.username, u.avatar_frame, GREATEST(0, COALESCE(SUM(e.amount),0)) wxp FROM challenge_joins j JOIN users u ON u.id = j.user_id LEFT JOIN xp_events e ON e.user_id = u.id AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) WHERE j.challenge_id = ? GROUP BY u.id ORDER BY wxp DESC LIMIT 6");
        $r->bind_param("i", $cid);
        $r->execute();
        $racers = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();
        if ($joined) {
            $m = $conn->prepare("SELECT GREATEST(0, COALESCE(SUM(amount),0)) wxp FROM xp_events WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $m->bind_param("i", $user_id);
            $m->execute();
            $my_race = (int)($m->get_result()->fetch_assoc()['wxp'] ?? 0);
            $m->close();
        }
    } catch (Throwable $e) {}
}
$conn->close();
$pages = max(1, (int)ceil($total / $per));
$page_title = 'Leaderboard';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Opt-in · tanpa email<?= $my_rank ? ' · peringkat #' . $my_rank . ($scope === 'week' ? ' minggu ini' : '') : '' ?></div>
        <h1 class="page-title">Leaderboard</h1>
        <p class="page-desc">Peringkat XP antar peserta. Ikut tampil? Aktifkan dari Profil.</p>
        <div class="page-actions leaderboard-actions">
            <div class="segmented" role="group" aria-label="Rentang leaderboard">
                <a href="leaderboard.php?scope=total" class="filter-pill <?= $scope === 'total' ? 'active' : '' ?>">Total XP</a>
                <a href="leaderboard.php?scope=week" class="filter-pill <?= $scope === 'week' ? 'active' : '' ?>">Minggu ini</a>
            </div>
            <a href="profile.php" class="page-actions-link">Visibilitas <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
    </div>
    <?php if ($challenge): $chtarget = (int)$challenge['target_xp']; ?>
    <section class="card p-4 mb-3" aria-label="Tantangan mingguan">
        <div class="ana-head">
            <div>
                <h2><?= htmlspecialchars($challenge['title']) ?></h2>
                <p>Target <?= $chtarget ?> XP minggu ini · <?= count($racers) ?> peserta · reset tiap Senin</p>
            </div>
        </div>
        <?php if ($joined && $my_race !== null): $mypct = challenge_pct($my_race, $chtarget); ?>
        <div class="ana-row" style="grid-template-columns:1fr auto;"><span class="ana-lbl">Progresmu: +<?= $my_race ?> XP</span><span class="ana-val"><?= $mypct ?>%<?= $mypct >= 100 ? ' · tuntas!' : '' ?></span></div>
        <div class="ana-track mb-2" role="progressbar" aria-valuenow="<?= $mypct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres tantangan <?= $mypct ?> persen"><span class="ana-fill" style="width:<?= $mypct ?>%"></span></div>
        <?php endif; ?>
        <?php if ($racers): ?>
        <div class="race-list">
            <?php $rp = 0; foreach ($racers as $rc): $rp++; $rwx = (int)$rc['wxp']; $rpct = challenge_pct($rwx, $chtarget); $isme = ((int)$rc['id'] === $user_id); ?>
            <div class="race-row<?= $isme ? ' me' : '' ?>">
                <span class="race-rank">#<?= $rp ?></span>
                <span class="avatar-circle avatar-sm frame-<?= htmlspecialchars($rc['avatar_frame'] ?? 'default') ?>" aria-hidden="true"><?= strtoupper(substr($rc['username'], 0, 1)) ?></span>
                <span class="race-name"><?= htmlspecialchars($rc['username']) ?><?= $isme ? ' (kamu)' : '' ?></span>
                <span class="ana-track"><span class="ana-fill" style="width:<?= $rpct ?>%"></span></span>
                <span class="ana-val">+<?= $rwx ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-secondary small mb-2">Belum ada peserta. Jadilah yang pertama.</p>
        <?php endif; ?>
        <form method="POST" action="leaderboard.php" class="m-0 mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
            <input type="hidden" name="challenge_action" value="<?= $joined ? 'leave' : 'join' ?>">
            <button type="submit" class="btn <?= $joined ? 'btn-cyber-outline' : 'btn-cyber' ?> btn-sm w-100"><?= $joined ? 'Keluar tantangan' : 'Ikut tantangan minggu ini' ?></button>
        </form>
    </section>
    <?php endif; ?>
    <div class="card p-2">
        <?php if (!$rows): ?><p class="text-secondary small p-3 mb-0">Belum ada peserta. Jadilah yang pertama dari Profil.</p><?php endif; ?>
        <?php $rank = $off; foreach ($rows as $r): $rank++; $lv = calculate_level($r['xp']); ?>
        <div class="list-row">
            <span class="avatar-circle avatar-sm frame-<?= htmlspecialchars($r['avatar_frame'] ?? 'default') ?>" aria-hidden="true"><?= strtoupper(substr($r['username'], 0, 1)) ?></span>
            <strong class="me-1" style="min-width:36px">#<?= $rank ?></strong>
            <div class="list-main"><p class="list-title"><?php if (!empty($r['public_profile'])): ?><a class="board-link" href="u.php?u=<?= urlencode($r['username']) ?>"><?= htmlspecialchars($r['username']) ?></a><?php else: ?><?= htmlspecialchars($r['username']) ?><?php endif; ?><?php if (!empty($r['flair'])): ?> <span class="flair-badge"><?= htmlspecialchars($r['flair']) ?></span><?php endif; ?> · Lv <?= $lv ?></p><p class="list-meta"><?= $scope === 'week' ? (int)$r['wxp'] . ' XP minggu ini · ' : '' ?><?= (int)$r['xp'] ?> XP · <?= (int)$r['streak'] ?> streak · <?= (int)$r['qd'] ?> quest</p></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
    <div class="d-flex gap-2 mt-3">
        <?php if ($page > 1): ?><a class="btn btn-cyber-outline btn-sm" href="leaderboard.php?scope=<?= $scope ?>&page=<?= $page - 1 ?>">‹ Sebelumnya</a><?php endif; ?>
        <span class="small text-muted align-self-center">Hal <?= $page ?>/<?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-cyber-outline btn-sm" href="leaderboard.php?scope=<?= $scope ?>&page=<?= $page + 1 ?>">Berikutnya ›</a><?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
