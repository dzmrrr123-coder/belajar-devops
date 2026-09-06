<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
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
