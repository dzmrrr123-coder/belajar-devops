<?php
// Tab Kuis Kilat (blitz). Mode latihan/review digabung ke review.php.
$mode = 'blitz';
$quiz_topics = quiz_topics(user_track($conn, $uid));
$topic = in_array($_GET['topic'] ?? 'all', $quiz_topics, true) ? $_GET['topic'] : 'all';
$track_in = "'" . implode("','", array_map(fn($t) => str_replace("'", "''", $t), $quiz_topics)) . "'";
$topic_sql = $topic === 'all' ? "AND c.topic IN ($track_in)" : 'AND c.topic = ?';
$done = !empty($_GET['done']);
$ids = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))));

$total_cards = 0;
try {
    $c = $conn->prepare("SELECT COUNT(*) n FROM quiz_cards WHERE user_id = ?");
    $c->bind_param("i", $uid); $c->execute();
    $total_cards = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) {}

$due_count = 0;
try {
    $c = $conn->prepare("SELECT COUNT(*) n FROM reviews WHERE user_id = ? AND source = 'quiz' AND next_due <= CURDATE()");
    $c->bind_param("i", $uid); $c->execute();
    $due_count = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) {}

$quiz_xp_today = 0;
try {
    $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
    $c->bind_param("i", $uid); $c->execute();
    $quiz_xp_today = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) {}
$quiz_quota_left = min(max(0, QUIZ_DAILY_XP_CAP - $quiz_xp_today), $lab_quota_left);

if (!$done && empty($ids)) {
    try {
        $s = $conn->prepare("SELECT c.id FROM quiz_cards c WHERE c.user_id = ? " . $topic_sql . " AND " . QUIZ_TODAY_DONE_SQL . " ORDER BY RAND() LIMIT 10");
        if ($topic === 'all') { $s->bind_param("i", $uid); } else { $s->bind_param("is", $uid, $topic); }
        $s->execute();
        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $ids[] = (int)$r['id'];
        $s->close();
    } catch (Throwable $e) {}
    if (!empty($ids)) {
        $_SESSION['quiz_run'] = ['tahu' => 0, 'lupa' => 0, 'xp' => 0];
        $_SESSION['blitz_deadline'] = time() + 60;
        redirect('lab.php?tab=kuis&mode=blitz&ids=' . implode(',', $ids) . '&i=0');
    }
}

$blitz_left = 0;
if (!$done && !empty($ids)) {
    $blitz_left = max(0, (int)(($_SESSION['blitz_deadline'] ?? 0) - time()));
    if ($blitz_left <= 0) redirect('lab.php?tab=kuis&mode=blitz&done=1');
}
$blitz_top = []; $blitz_best = null;
try {
    $bt = $conn->prepare("SELECT u.username, MAX(b.score) s FROM blitz_runs b JOIN users u ON u.id = b.user_id WHERE b.created_at >= CURDATE() AND b.created_at < CURDATE() + INTERVAL 1 DAY GROUP BY u.id, u.username ORDER BY s DESC LIMIT 5");
    if ($bt) { $bt->execute(); $blitz_top = $bt->get_result()->fetch_all(MYSQLI_ASSOC); $bt->close(); }
    $bb = $conn->prepare("SELECT MAX(score) s FROM blitz_runs WHERE user_id = ? AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
    if ($bb) { $bb->bind_param("i", $uid); $bb->execute(); $blitz_best = $bb->get_result()->fetch_assoc()['s'] ?? null; $bb->close(); }
} catch (Throwable $e) {}

$card = null;
$i = 0;
if (!$done && !empty($ids)) {
    $i = max(0, min(count($ids) - 1, (int)($_GET['i'] ?? 0)));
    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, question, answer, created_at FROM quiz_cards WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ids[$i], $uid);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$card) redirect('lab.php?tab=kuis&mode=blitz');
}
$run = $_SESSION['quiz_run'] ?? ['tahu' => 0, 'lupa' => 0, 'xp' => 0];
if ($done) { $_SESSION['quiz_run'] = ['tahu' => 0, 'lupa' => 0, 'xp' => 0]; }
?>
<div class="quiz-page">
    <div class="page-actions leaderboard-actions mb-3">
        <div class="segmented" role="group" aria-label="Mode kuis">
            <a href="lab.php?tab=kuis&mode=blitz" class="filter-pill active">Kilat 60 dtk</a>
            <a href="review.php" class="filter-pill">Review (<?= $due_count ?>)</a>
        </div>
        <div class="segmented" role="group" aria-label="Topik kuis">
            <a href="lab.php?tab=kuis&mode=blitz" class="filter-pill <?= $topic === 'all' ? 'active' : '' ?>">Semua</a>
            <?php foreach ($quiz_topics as $t): ?>
            <a href="lab.php?tab=kuis&mode=blitz&topic=<?= urlencode($t) ?>" class="filter-pill <?= $topic === $t ? 'active' : '' ?>"><?= htmlspecialchars($t) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($total_cards === 0): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-brain" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold">Belum ada kartu kuis</h2>
        <p class="text-secondary small mb-3">Buat dari catatan errormu — cukup sekali ketuk.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="errors.php" class="btn btn-cyber-outline btn-sm">Ke Notes</a>
            <a href="review.php" class="btn btn-cyber-outline btn-sm">Ke Review</a>
        </div>
    </div>
    <?php elseif ($done): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-flag-checkered" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold">Waktu habis!</h2>
        <p class="text-secondary small mb-3">Tahu <?= (int)($run['tahu'] ?? 0) ?> · Lupa <?= (int)($run['lupa'] ?? 0) ?> · +<?= (int)($run['xp'] ?? 0) ?> XP sesi ini.<?= $blitz_best !== null ? ' · Terbaik hari ini: ' . (int)$blitz_best : '' ?></p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="lab.php?tab=kuis&mode=blitz" class="btn btn-cyber btn-sm">Main lagi</a>
            <a href="review.php" class="btn btn-cyber-outline btn-sm">Ke Review</a>
        </div>
    </div>
    <?php if ($blitz_top): ?>
    <section class="card p-4 mt-3" aria-label="Tercepat hari ini">
        <h2 class="h5 fw-bold mb-1">Tercepat hari ini</h2>
        <p class="text-secondary small mb-3">Skor kilat tertinggi per user.</p>
        <div class="race-list">
            <?php $bp = 0; foreach ($blitz_top as $brow): $bp++; ?>
            <div class="race-row">
                <span class="race-rank">#<?= $bp ?></span>
                <span class="race-name"><?= htmlspecialchars($brow['username']) ?></span>
                <span class="ana-val"><?= (int)$brow['s'] ?> benar</span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php elseif (empty($ids)): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-check-double" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold">Tuntas hari ini!</h2>
        <p class="text-secondary small mb-3">Semua kartu sudah dijawab. Kembali besok untuk +XP lagi.</p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="errors.php" class="btn btn-cyber-outline btn-sm">Ke Notes</a>
        </div>
    </div>
    <?php elseif ($card): ?>
    <div class="card p-3 mb-3" role="group" aria-label="Sisa waktu kilat">
        <div class="d-flex justify-content-between align-items-center mb-1"><strong><i class="fas fa-bolt me-1" aria-hidden="true"></i>Mode kilat</strong><span id="blitzClock"><?= $blitz_left ?> dtk</span></div>
        <div class="review-progress-bar" role="progressbar" aria-valuenow="<?= $blitz_left ?>" aria-valuemin="0" aria-valuemax="60" aria-label="Sisa waktu"><div id="blitzBar" style="width: <?= (int)round($blitz_left / 60 * 100) ?>%;"></div></div>
    </div>
    <script>
    (function() {
        let left = <?= $blitz_left ?>;
        const clock = document.getElementById('blitzClock');
        const bar = document.getElementById('blitzBar');
        const t = setInterval(function() {
            left--;
            if (clock) clock.textContent = Math.max(0, left) + ' dtk';
            if (bar) bar.style.width = Math.max(0, Math.round(left / 60 * 100)) + '%';
            if (left <= 0) { clearInterval(t); window.location.href = 'lab.php?tab=kuis&mode=blitz&done=1'; }
        }, 1000);
    })();
    </script>
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
                <form method="POST" action="lab.php?tab=kuis" class="review-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="answer">
                    <input type="hidden" name="card_id" id="quizCardId" value="<?= (int)$card['id'] ?>">
                    <input type="hidden" name="mode" value="blitz">
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
</div>
