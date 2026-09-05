<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rid = (int)($_POST['review_id'] ?? 0);
    $result = $_POST['result'] ?? '';
    if ($rid > 0 && in_array($result, ['know', 'forgot'], true)) {
        $stmt = $conn->prepare("SELECT * FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $rid, $user_id);
        $stmt->execute();
        $rev = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($rev) {
            if ($result === 'know') {
                $next = review_next_interval((int)$rev['interval_day']);
                $up = $conn->prepare("UPDATE reviews SET interval_day = ?, next_due = DATE_ADD(CURDATE(), INTERVAL ? DAY), done_count = done_count + 1 WHERE id = ? AND user_id = ?");
                $up->bind_param("iiii", $next, $next, $rid, $user_id);
                $up->execute(); $up->close();
                if ((int)$rev['done_count'] === 0) {
                    $xp = 5;
                    $x = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
                    $x->bind_param("ii", $xp, $user_id); $x->execute(); $x->close();
                    $nb = check_and_unlock_badges($conn, $user_id);
                    set_flash('success', 'Review tuntas! +5 XP. Jadwal berikutnya diperpanjang.' . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : ''));
                } else {
                    check_and_unlock_badges($conn, $user_id);
                    set_flash('success', 'Bagus! Jadwal review diperpanjang.');
                }
            } else {
                $up = $conn->prepare("UPDATE reviews SET interval_day = 1, next_due = DATE_ADD(CURDATE(), INTERVAL 1 DAY), lapses = lapses + 1 WHERE id = ? AND user_id = ?");
                $up->bind_param("ii", $rid, $user_id);
                $up->execute(); $up->close();
                set_flash('info', 'Dicatat. Kita ulang besok agar nempel.');
            }
        }
    }
    redirect('review.php');
}
$stmt = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND next_due <= CURDATE()");
$stmt->bind_param("i", $user_id); $stmt->execute();
$due_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$stmt = $conn->prepare("SELECT * FROM reviews WHERE user_id = ? AND next_due <= CURDATE() ORDER BY next_due ASC, id ASC LIMIT 20");
$stmt->bind_param("i", $user_id); $stmt->execute();
$due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$current = $due[0] ?? null;
$conn->close();
$page_title = 'Review Inbox';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker"><?= $due_count ?> perlu direview hari ini</div>
        <h1 class="page-title">Review Inbox</h1>
        <p class="page-desc">Satu kartu satu waktu. Jujur saja — lupa itu normal, sistem atur ulang otomatis (1-3-7-14-30 hari).</p>
        <div class="page-actions">
            <a href="index.php" class="btn btn-cyber-outline">Dashboard</a>
            <a href="quests.php" class="btn btn-cyber-outline">Roadmap</a>
        </div>
    </div>
    <?php if (!$current): ?>
    <div class="empty-state card p-5">
        <div class="empty-state-icon"><i class="fas fa-check-double"></i></div>
        <h2 class="h5 fw-bold">Bersih! Tidak ada review jatuh tempo.</h2>
        <p class="text-secondary small mb-0">Selesaikan quest atau tulis catatan — otomatis masuk antrean review besok.</p>
    </div>
    <?php else: ?>
    <div class="row g-4 justify-content-center">
        <div class="col-lg-7">
            <article class="card p-4 review-card">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="question-status status-in_review"><?= htmlspecialchars($current['source']) ?></span>
                    <span class="text-secondary small">Jatuh tempo <?= htmlspecialchars($current['next_due']) ?> · interval <?= (int)$current['interval_day'] ?> hari</span>
                </div>
                <h2 class="h5 fw-bold"><?= htmlspecialchars($current['title']) ?></h2>
                <?php if (!empty($current['detail'])): ?><p class="text-secondary"><?= nl2br(htmlspecialchars(mb_strimwidth($current['detail'], 0, 400, '...'))) ?></p><?php endif; ?>
                <form method="POST" action="review.php" class="review-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="review_id" value="<?= (int)$current['id'] ?>">
                    <button type="submit" name="result" value="forgot" class="btn btn-cyber-outline flex-fill"><i class="fas fa-rotate-left me-1"></i>Lupa, ulang besok</button>
                    <button type="submit" name="result" value="know" class="btn btn-cyber flex-fill"><i class="fas fa-check me-1"></i>Tahu, lanjut</button>
                </form>
                <p class="small text-muted mt-3 mb-0">Sisa <?= $due_count - 1 ?> kartu lagi setelah ini.</p>
            </article>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
