<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Anti-farm: XP kuis maksimal 20/hari, dan kartu yang sudah dijawab Tahu
// hari ini tidak ditawarkan lagi (cek di query latihan/review di bawah).
define('QUIZ_DAILY_XP_CAP', 20);
define('QUIZ_TODAY_DONE_SQL', "NOT EXISTS (SELECT 1 FROM xp_events e WHERE e.user_id = c.user_id AND e.ref_type = 'quiz' AND e.ref_id = c.id AND e.amount > 0 AND e.created_at >= CURDATE() AND e.created_at < CURDATE() + INTERVAL 1 DAY)");

// Tambah kartu kuis dari Notes / Questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf();
    $source = in_array($_POST['source'] ?? '', ['error', 'question'], true) ? $_POST['source'] : 'error';
    $source_id = (int)($_POST['source_id'] ?? 0);
    $question = mb_substr(trim(clean($_POST['question'] ?? '')), 0, 255);
    $answer = mb_substr(trim(clean($_POST['answer'] ?? '')), 0, 2000);
    $back = ($_POST['back'] ?? '') === 'questions.php' ? 'questions.php' : 'errors.php';
    if ($question === '' || $answer === '' || $source_id <= 0) {
        set_flash('warning', 'Pertanyaan, jawaban, dan sumber wajib diisi.');
    } else {
        $ins = $conn->prepare("INSERT INTO quiz_cards (user_id, source, source_id, question, answer) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer)");
        $ins->bind_param("isiss", $user_id, $source, $source_id, $question, $answer);
        if ($ins->execute()) {
            $cid = $source_id > 0 ? (int)$conn->insert_id : 0;
            if ($cid <= 0) {
                $g = $conn->prepare("SELECT id FROM quiz_cards WHERE user_id = ? AND source = ? AND source_id = ?");
                $g->bind_param("isi", $user_id, $source, $source_id);
                $g->execute();
                $cid = (int)($g->get_result()->fetch_assoc()['id'] ?? 0);
                $g->close();
            }
            if ($cid > 0) schedule_review($conn, $user_id, 'quiz', $cid, $question, $answer);
            set_flash('success', 'Kartu kuis disimpan. Selamat berlatih!');
        } else {
            set_flash('danger', 'Gagal menyimpan kartu kuis.');
        }
        $ins->close();
    }
    redirect($back);
}

// Jawab kartu: Tahu (+2 XP sekali per kartu per hari) / Lupa (jadwal ulang besok)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'answer') {
    verify_csrf();
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    $card_id = (int)($_POST['card_id'] ?? 0);
    $result = ($_POST['result'] ?? '') === 'know' ? 'know' : 'forgot';
    $mode = ($_POST['mode'] ?? '') === 'review' ? 'review' : 'latihan';
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
    $i = max(0, (int)($_POST['i'] ?? 0));
    $run = $_SESSION['quiz_run'] ?? ['tahu' => 0, 'lupa' => 0, 'xp' => 0];

    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, question, answer, created_at FROM quiz_cards WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $card_id, $user_id);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($card) {
        if ($result === 'know') {
            $chk = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND ref_id = ? AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
            $chk->bind_param("ii", $user_id, $card_id);
            $chk->execute();
            $already = (int)($chk->get_result()->fetch_assoc()['n'] ?? 0);
            $chk->close();
            if ($already <= 0) {
                $rev = $conn->prepare("SELECT interval_day, done_count FROM reviews WHERE user_id = ? AND source = 'quiz' AND source_id = ?");
                $rev->bind_param("ii", $user_id, $card_id);
                $rev->execute();
                $rrow = $rev->get_result()->fetch_assoc();
                $rev->close();
                $next = review_next_interval((int)($rrow['interval_day'] ?? 1));
                $rtitle = mb_substr(trim((string)($card['question'] ?? 'Kuis')), 0, 255);
                $rdetail = mb_substr((string)($card['answer'] ?? ''), 0, 2000);
                $up = $conn->prepare("INSERT INTO reviews (user_id, source, source_id, title, detail, next_due, interval_day, done_count) VALUES (?, 'quiz', ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 1) ON DUPLICATE KEY UPDATE interval_day = VALUES(interval_day), next_due = VALUES(next_due), done_count = done_count + 1");
                $up->bind_param("iissii", $user_id, $card_id, $rtitle, $rdetail, $next, $next);
                $up->execute(); $up->close();
                $cap = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
                $cap->bind_param("i", $user_id);
                $cap->execute();
                $today_sum = (int)($cap->get_result()->fetch_assoc()['n'] ?? 0);
                $cap->close();
                $gain = capped_xp_gain(2, $today_sum, QUIZ_DAILY_XP_CAP);
                if ($gain > 0) {
                    award_xp($conn, $user_id, $gain, 'quiz', 'quiz', $card_id);
                    $run['xp'] = ($run['xp'] ?? 0) + $gain;
                }
                check_and_unlock_badges($conn, $user_id);
            }
            $run['tahu'] = ($run['tahu'] ?? 0) + 1;
        } else {
            $up = $conn->prepare("UPDATE reviews SET interval_day = 1, next_due = DATE_ADD(CURDATE(), INTERVAL 1 DAY), lapses = lapses + 1 WHERE user_id = ? AND source = 'quiz' AND source_id = ?");
            $up->bind_param("ii", $user_id, $card_id);
            $up->execute(); $up->close();
            $run['lupa'] = ($run['lupa'] ?? 0) + 1;
        }
    }
    $_SESSION['quiz_run'] = $run;
    $next_i = $i + 1;
    if (!empty($is_ajax)) {
        $qc = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
        $qc->bind_param("i", $user_id); $qc->execute();
        $quota_left = max(0, QUIZ_DAILY_XP_CAP - (int)($qc->get_result()->fetch_assoc()['n'] ?? 0));
        $qc->close();
        $next_card = null;
        if ($next_i < count($ids)) {
            $stmt = $conn->prepare("SELECT id, question, answer FROM quiz_cards WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $ids[$next_i], $user_id);
            $stmt->execute();
            $next_card = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $conn->close();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 'result' => $result, 'gain' => $gain ?? 0, 'run' => $run,
            'quota_left' => $quota_left, 'pos' => $next_i + 1, 'total' => count($ids), 'next' => $next_card,
        ]);
        exit();
    }
    if ($next_i >= count($ids)) {
        redirect('quiz.php?mode=' . $mode . '&done=1');
    }
    redirect('quiz.php?mode=' . $mode . '&ids=' . implode(',', $ids) . '&i=' . $next_i);
}

// Daftar kartu sesi ini
$mode = ($_GET['mode'] ?? '') === 'review' ? 'review' : 'latihan';
$quiz_topics = quiz_topics();
$topic = in_array($_GET['topic'] ?? 'all', $quiz_topics, true) ? $_GET['topic'] : 'all';
$topic_sql = $topic === 'all' ? '' : 'AND c.topic = ?';
$done = !empty($_GET['done']);
$ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));

$total_cards = 0;
try {
    $c = $conn->prepare("SELECT COUNT(*) n FROM quiz_cards WHERE user_id = ?");
    $c->bind_param("i", $user_id); $c->execute();
    $total_cards = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) {}

$due_count = 0;
try {
    $c = $conn->prepare("SELECT COUNT(*) n FROM reviews WHERE user_id = ? AND source = 'quiz' AND next_due <= CURDATE()");
    $c->bind_param("i", $user_id); $c->execute();
    $due_count = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) {}

$quiz_xp_today = 0;
try {
    $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
    $c->bind_param("i", $user_id); $c->execute();
    $quiz_xp_today = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) {}
$quiz_quota_left = max(0, QUIZ_DAILY_XP_CAP - $quiz_xp_today);

if (!$done && empty($ids)) {
    if ($mode === 'review') {
        try {
            $s = $conn->prepare("SELECT c.id FROM quiz_cards c JOIN reviews r ON r.source = 'quiz' AND r.source_id = c.id AND r.user_id = c.user_id WHERE c.user_id = ? AND r.next_due <= CURDATE() " . $topic_sql . " AND " . QUIZ_TODAY_DONE_SQL . " ORDER BY r.next_due ASC, c.id ASC LIMIT 20");
            if ($topic === 'all') { $s->bind_param("i", $user_id); } else { $s->bind_param("is", $user_id, $topic); }
            $s->execute();
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $ids[] = (int)$r['id'];
            $s->close();
        } catch (Throwable $e) {}
    } else {
        try {
            $s = $conn->prepare("SELECT c.id FROM quiz_cards c WHERE c.user_id = ? " . $topic_sql . " AND " . QUIZ_TODAY_DONE_SQL . " ORDER BY RAND() LIMIT 10");
            if ($topic === 'all') { $s->bind_param("i", $user_id); } else { $s->bind_param("is", $user_id, $topic); }
            $s->execute();
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $ids[] = (int)$r['id'];
            $s->close();
        } catch (Throwable $e) {}
    }
    if (!empty($ids)) {
        $_SESSION['quiz_run'] = ['tahu' => 0, 'lupa' => 0, 'xp' => 0];
        redirect('quiz.php?mode=' . $mode . '&ids=' . implode(',', $ids) . '&i=0');
    }
}

$card = null;
$i = 0;
if (!$done && !empty($ids)) {
    $i = max(0, min(count($ids) - 1, (int)($_GET['i'] ?? 0)));
    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, question, answer, created_at FROM quiz_cards WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ids[$i], $user_id);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$card) redirect('quiz.php?mode=' . $mode);
}
$run = $_SESSION['quiz_run'] ?? ['tahu' => 0, 'lupa' => 0, 'xp' => 0];
if ($done) { $_SESSION['quiz_run'] = ['tahu' => 0, 'lupa' => 0, 'xp' => 0]; }
$conn->close();

$page_title = 'Kuis';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4 quiz-page" role="main">
    <div class="page-head">
        <div class="page-kicker"><?= $total_cards ?> kartu · <?= $due_count ?> jatuh tempo · kuota <span id="quizQuota">+<?= $quiz_quota_left ?> XP</span> hari ini</div>
        <h1 class="page-title">Kuis</h1>
        <p class="page-desc">Uji ingatanmu satu kartu satu waktu. Tahu +2 XP — maks +<?= QUIZ_DAILY_XP_CAP ?> XP/hari, kartu tuntas tak muncul lagi.</p>
        <div class="page-actions leaderboard-actions">
            <div class="segmented" role="group" aria-label="Mode kuis">
                <a href="quiz.php?mode=latihan<?= $topic !== 'all' ? '&topic=' . urlencode($topic) : '' ?>" class="filter-pill <?= $mode === 'latihan' ? 'active' : '' ?>">Latihan acak</a>
                <a href="quiz.php?mode=review<?= $topic !== 'all' ? '&topic=' . urlencode($topic) : '' ?>" class="filter-pill <?= $mode === 'review' ? 'active' : '' ?>">Review (<?= $due_count ?>)</a>
            </div>
            <div class="segmented" role="group" aria-label="Topik kuis">
                <a href="quiz.php?mode=<?= $mode ?>" class="filter-pill <?= $topic === 'all' ? 'active' : '' ?>">Semua</a>
                <?php foreach ($quiz_topics as $t): ?>
                <a href="quiz.php?mode=<?= $mode ?>&topic=<?= urlencode($t) ?>" class="filter-pill <?= $topic === $t ? 'active' : '' ?>"><?= htmlspecialchars($t) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($total_cards === 0): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-brain" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold">Belum ada kartu kuis</h2>
        <p class="text-secondary small mb-3">Buat dari catatan error atau pertanyaanmu — cukup sekali ketuk.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="errors.php" class="btn btn-cyber-outline btn-sm">Ke Notes</a>
            <a href="questions.php" class="btn btn-cyber-outline btn-sm">Ke Questions</a>
        </div>
    </div>
    <?php elseif ($done): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-flag-checkered" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold">Sesi selesai!</h2>
        <p class="text-secondary small mb-3">Tahu <?= (int)($run['tahu'] ?? 0) ?> · Lupa <?= (int)($run['lupa'] ?? 0) ?> · +<?= (int)($run['xp'] ?? 0) ?> XP sesi ini.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="quiz.php?mode=latihan" class="btn btn-cyber btn-sm">Main lagi</a>
            <a href="review.php" class="btn btn-cyber-outline btn-sm">Ke Review</a>
        </div>
    </div>
    <?php elseif (empty($ids)): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-check-double" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold"><?= $total_cards > 0 ? 'Tuntas hari ini!' : 'Tidak ada kartu ' . ($mode === 'review' ? 'jatuh tempo' : 'untuk latihan') . '.' ?></h2>
        <p class="text-secondary small mb-3"><?= $total_cards > 0 ? 'Semua kartu sudah dijawab. Kembali besok untuk +XP lagi.' : ($mode === 'review' ? 'Semua terjadwal rapi. Coba mode latihan.' : 'Tambah kartu dari Notes atau Questions dulu.') ?></p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <?php if ($mode === 'review'): ?><a href="quiz.php?mode=latihan" class="btn btn-cyber btn-sm">Latihan acak</a><?php endif; ?>
            <a href="errors.php" class="btn btn-cyber-outline btn-sm">Ke Notes</a>
        </div>
    </div>
    <?php elseif ($card): ?>
    <div class="row g-4 justify-content-center">
        <div class="col-lg-7">
            <article class="card p-4 quiz-card">
                <div class="review-progress">
                    <span id="quizPos">Kartu <?= $i + 1 ?> dari <?= count($ids) ?></span>
                    <div class="review-progress-bar" role="progressbar" aria-valuenow="<?= $i + 1 ?>" aria-valuemin="0" aria-valuemax="<?= count($ids) ?>" aria-label="Progres kuis"><div id="quizBar" style="width: <?= (int)round((($i + 1) / max(1, count($ids))) * 100) ?>%;"></div></div>
                </div>
                <p class="quiz-kicker">Ingat-ingat dulu, baru buka jawabannya</p>
                <h2 class="h5 fw-bold quiz-question" id="quizQ"><?= htmlspecialchars($card['question']) ?></h2>
                <details class="quiz-answer" id="quizDetails">
                    <summary class="quiz-answer-toggle"><i class="fas fa-eye me-1" aria-hidden="true"></i>Lihat jawaban</summary>
                    <div class="code-solution" id="quizA"><?= nl2br(htmlspecialchars($card['answer'])) ?></div>
                </details>
                <form method="POST" action="quiz.php" class="review-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="answer">
                    <input type="hidden" name="card_id" id="quizCardId" value="<?= (int)$card['id'] ?>">
                    <input type="hidden" name="mode" value="<?= $mode ?>">
                    <input type="hidden" name="ids" value="<?= htmlspecialchars(implode(',', $ids)) ?>">
                    <input type="hidden" name="i" id="quizI" value="<?= $i ?>">
                    <button type="submit" name="result" value="forgot" class="btn btn-cyber-outline flex-fill"><i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Lupa</button>
                    <button type="submit" name="result" value="know" class="btn btn-cyber flex-fill"><i class="fas fa-check me-1" aria-hidden="true"></i>Tahu<span id="quizTahuXp"><?= $quiz_quota_left > 0 ? ' (+' . min(2, $quiz_quota_left) . ' XP)' : '' ?></span></button>
                </form>
                <p class="small text-muted mt-3 mb-0" id="quizRun">Sesi ini: Tahu <?= (int)($run['tahu'] ?? 0) ?> · Lupa <?= (int)($run['lupa'] ?? 0) ?> · +<?= (int)($run['xp'] ?? 0) ?> XP</p>
            </article>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
