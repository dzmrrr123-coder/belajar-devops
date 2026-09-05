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
        $s = $conn->prepare("SELECT u.username, u.xp, u.streak, COALESCE((SELECT SUM(e.amount) FROM xp_events e WHERE e.user_id = u.id AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND e.amount > 0), 0) wxp, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd FROM users u WHERE u.show_on_board = 1 ORDER BY wxp DESC, u.streak DESC LIMIT ? OFFSET ?");
    } else {
        $s = $conn->prepare("SELECT username, xp, streak, xp AS wxp, (SELECT COUNT(*) FROM user_quests WHERE user_id = users.id) qd FROM users WHERE show_on_board = 1 ORDER BY xp DESC, streak DESC LIMIT ? OFFSET ?");
    }
    $s->bind_param("ii", $per, $off);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
} catch (Throwable $e) {}
$my_rank = null;
$on_board = false;
try {
    $c = $conn->prepare("SELECT show_on_board FROM users WHERE id = ?");
    $c->bind_param("i", $user_id); $c->execute();
    $on_board = !empty($c->get_result()->fetch_assoc()['show_on_board']);
    $c->close();
} catch (Throwable $e) {}
if ($on_board) {
    try {
        $s = $conn->prepare("SELECT COUNT(*) c FROM users WHERE show_on_board = 1 AND xp > (SELECT xp FROM users WHERE id = ?)");
        $s->bind_param("i", $user_id); $s->execute();
        $my_rank = (int)($s->get_result()->fetch_assoc()['c'] ?? 0) + 1;
        $s->close();
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
        <div class="page-kicker">Opt-in · tanpa email<?= $my_rank ? ' · peringkat #' . $my_rank : '' ?></div>
        <h1 class="page-title">Leaderboard</h1>
        <p class="page-desc">Hanya yang mengaktifkan “Tampil di leaderboard” di Profil.</p>
        <div class="page-actions">
            <a href="leaderboard.php?scope=total" class="btn <?= $scope === 'total' ? 'btn-cyber' : 'btn-cyber-outline' ?> btn-sm">Total XP</a>
            <a href="leaderboard.php?scope=week" class="btn <?= $scope === 'week' ? 'btn-cyber' : 'btn-cyber-outline' ?> btn-sm">Minggu ini</a>
            <a href="profile.php" class="btn btn-cyber-outline btn-sm">Visibilitas</a>
        </div>
    </div>
    <div class="card p-2">
        <?php if (!$rows): ?><p class="text-secondary small p-3 mb-0">Belum ada peserta. Jadilah yang pertama dari Profil.</p><?php endif; ?>
        <?php $rank = $off; foreach ($rows as $r): $rank++; $lv = calculate_level($r['xp']); ?>
        <div class="list-row">
            <strong class="me-1" style="min-width:36px">#<?= $rank ?></strong>
            <div class="list-main"><p class="list-title"><?= htmlspecialchars($r['username']) ?> · Lv <?= $lv ?></p><p class="list-meta"><?= $scope === 'week' ? (int)$r['wxp'] . ' XP minggu ini · ' : '' ?><?= (int)$r['xp'] ?> XP · <?= (int)$r['streak'] ?> streak · <?= (int)$r['qd'] ?> quest</p></div>
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
