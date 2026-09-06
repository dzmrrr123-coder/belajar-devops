<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    $rid = (int)($_POST['review_id'] ?? 0);
    $result = $_POST['result'] ?? '';
    if ($rid > 0 && in_array($result, ['know', 'forgot'], true)) {
        $stmt = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $rid, $user_id);
        $stmt->execute();
        $rev = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($rev) {
            $xp_gain = 0; $nb = [];
            if ($result === 'know') {
                $next = review_next_interval((int)$rev['interval_day']);
                $up = $conn->prepare("UPDATE reviews SET interval_day = ?, next_due = DATE_ADD(CURDATE(), INTERVAL ? DAY), done_count = done_count + 1 WHERE id = ? AND user_id = ?");
                $up->bind_param("iiii", $next, $next, $rid, $user_id);
                $up->execute(); $up->close();
                if ((int)$rev['done_count'] === 0) {
                    $xp_gain = apply_xp_multiplier(5, mission_multiplier($conn, $user_id));
                    award_xp($conn, $user_id, $xp_gain, 'review', 'review', $rid);
                    $nb = check_and_unlock_badges($conn, $user_id);
                    $msg = "Review tuntas! +{$xp_gain} XP. Jadwal berikutnya diperpanjang." . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : '');
                    if (!$is_ajax) set_flash('success', $msg);
                } else {
                    $nb = check_and_unlock_badges($conn, $user_id);
                    $msg = 'Bagus! Jadwal review diperpanjang.' . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : '');
                    if (!$is_ajax) set_flash('success', $msg);
                }
            } else {
                $up = $conn->prepare("UPDATE reviews SET interval_day = 1, next_due = DATE_ADD(CURDATE(), INTERVAL 1 DAY), lapses = lapses + 1 WHERE id = ? AND user_id = ?");
                $up->bind_param("ii", $rid, $user_id);
                $up->execute(); $up->close();
                $msg = 'Dicatat. Kita ulang besok agar nempel.';
                if (!$is_ajax) set_flash('info', $msg);
            }
            if ($is_ajax) {
                $stmt = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND next_due <= CURDATE()");
                $stmt->bind_param("i", $user_id); $stmt->execute();
                $remaining = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
                $stmt->close();
                $next_card = null;
                if ($remaining > 0) {
                    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses FROM reviews WHERE user_id = ? AND next_due <= CURDATE() ORDER BY next_due ASC, id ASC LIMIT 1");
                    $stmt->bind_param("i", $user_id); $stmt->execute();
                    $next_card = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }
                $conn->close();
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success', 'result' => $result, 'message' => $msg ?? 'Tersimpan.',
                    'xp_gain' => $xp_gain, 'new_badges' => $nb,
                    'remaining' => $remaining, 'next' => $next_card,
                ]);
                exit();
            }
        }
    }
    redirect('review.php');
}
$stmt = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND next_due <= CURDATE()");
$stmt->bind_param("i", $user_id); $stmt->execute();
$due_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$stmt = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses FROM reviews WHERE user_id = ? AND next_due <= CURDATE() ORDER BY next_due ASC, id ASC LIMIT 20");
$stmt->bind_param("i", $user_id); $stmt->execute();
$due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$current = $due[0] ?? null;
$conn->close();
$due_label = 'hari ini';
if ($current) {
    $today = date('Y-m-d');
    $dd = (string)($current['next_due'] ?? $today);
    if ($dd < $today) {
        $late = max(1, (int)((strtotime($today) - strtotime($dd)) / 86400));
        $due_label = 'terlambat ' . $late . ' hari';
    } elseif ($dd > $today) {
        $due_label = date('d M Y', strtotime($dd));
    }
}
$page_title = 'Review Inbox';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker" id="reviewKicker"><?= $due_count ?> perlu direview hari ini</div>
        <h1 class="page-title">Review Inbox</h1>
        <p class="page-desc">Satu kartu satu waktu. Jujur saja — lupa itu normal, sistem atur ulang otomatis (1-3-7-14-30 hari).</p>
        <div class="page-actions review-back">
            <a href="index.php" class="page-actions-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>Dashboard</a>
        </div>
    </div>
    <?php if (!$current): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-check-double"></i></div>
        <h2 class="h5 fw-bold">Bersih! Tidak ada review jatuh tempo.</h2>
        <p class="text-secondary small mb-0">Selesaikan quest atau tulis catatan — otomatis masuk antrean review besok.</p>
    </div>
    <?php else: ?>
    <div class="row g-4 justify-content-center">
        <div class="col-lg-7">
            <article class="card p-4 review-card">
                <div class="review-progress">
                    <span id="reviewPos">Kartu 1 dari <?= $due_count ?></span>
                    <div class="review-progress-bar" role="progressbar" aria-valuenow="1" aria-valuemin="0" aria-valuemax="<?= $due_count ?>" aria-label="Posisi kartu review"><div id="reviewBar" style="width: <?= (int)round(100 / max(1, $due_count)) ?>%;"></div></div>
                </div>
                <div class="review-meta">
                    <span class="question-status status-in_review" id="reviewSource"><?= htmlspecialchars($current['source']) ?></span>
                    <span class="review-due" id="reviewDue"><?= htmlspecialchars($due_label) ?> · interval <?= (int)$current['interval_day'] ?> hari</span>
                </div>
                <h2 class="h5 fw-bold review-title" id="reviewTitle"><?= htmlspecialchars($current['title']) ?></h2>
                <div id="reviewDetail">
                <?php if (!empty($current['detail'])): ?>
                    <?php if (($current['source'] ?? '') === 'quiz'): ?>
                    <details class="quiz-answer">
                        <summary class="quiz-answer-toggle"><i class="fas fa-eye me-1" aria-hidden="true"></i>Lihat jawaban</summary>
                        <div class="code-solution"><?= nl2br(htmlspecialchars(mb_strimwidth($current['detail'], 0, 400, '...'))) ?></div>
                    </details>
                    <?php else: ?><p class="text-secondary"><?= nl2br(htmlspecialchars(mb_strimwidth($current['detail'], 0, 400, '...'))) ?></p><?php endif; ?>
                <?php endif; ?>
                </div>
                <form method="POST" action="review.php" class="review-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="review_id" id="reviewIdInput" value="<?= (int)$current['id'] ?>">
                    <button type="submit" name="result" value="forgot" class="btn btn-cyber-outline flex-fill"><i class="fas fa-rotate-left me-1"></i>Lupa, ulang besok</button>
                    <button type="submit" name="result" value="know" class="btn btn-cyber flex-fill"><i class="fas fa-check me-1"></i>Tahu, lanjut</button>
                </form>
                <p class="small text-muted mt-3 mb-0" id="reviewLeft">Sisa <?= $due_count - 1 ?> kartu lagi setelah ini.</p>
            </article>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
