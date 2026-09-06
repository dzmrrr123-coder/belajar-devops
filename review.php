<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$valid_grades = ['again', 'hard', 'good', 'easy', 'know', 'forgot'];
$grade_map = ['know' => 'good', 'forgot' => 'again'];
$deck_names = array_keys(skill_defs());
$deck = trim((string)($_GET['deck'] ?? $_POST['deck'] ?? 'Semua'));
if ($deck !== 'Semua' && !in_array($deck, $deck_names, true)) $deck = 'Semua';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    $rid = (int)($_POST['review_id'] ?? 0);
    if (rate_limit_hit('review_grade', 40, 60)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Terlalu cepat. Tunggu sebentar.']);
            exit();
        }
        set_flash('warning', 'Terlalu cepat. Tunggu sebentar.');
        redirect('review.php');
    }
    $grade = strtolower(trim((string)($_POST['result'] ?? $_POST['grade'] ?? '')));
    if ($rid > 0 && in_array($grade, $valid_grades, true)) {
        $grade = $grade_map[$grade] ?? $grade;
        $stmt = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses, ease_factor, reps, skill FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $rid, $user_id);
        $stmt->execute();
        $rev = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($rev) {
            $sm = sm2_next((float)($rev['ease_factor'] ?? 2.5), (int)($rev['reps'] ?? 0), (int)($rev['interval_day'] ?? 1), $grade);
            $next_int = (int)$sm['interval'];
            $new_ease = (float)$sm['ease'];
            $new_reps = (int)$sm['reps'];
            $passed = sm2_grade_to_int($grade) >= 3;
            $xp_gain = 0; $nb = [];
            if ($passed) {
                $up = $conn->prepare("UPDATE reviews SET interval_day = ?, next_due = DATE_ADD(CURDATE(), INTERVAL ? DAY), done_count = done_count + 1, ease_factor = ?, reps = ? WHERE id = ? AND user_id = ?");
                $up->bind_param("iidiii", $next_int, $next_int, $new_ease, $new_reps, $rid, $user_id);
                $up->execute(); $up->close();
                if ((int)$rev['done_count'] === 0) {
                    $xp_gain = apply_xp_multiplier(5, mission_multiplier($conn, $user_id));
                    award_xp($conn, $user_id, $xp_gain, 'review', 'review', $rid);
                }
                $nb = check_and_unlock_badges($conn, $user_id);
                $msg = $grade === 'easy' ? "Mudah! Jadwal mundur {$next_int} hari. +{$xp_gain} XP." : ($grade === 'hard' ? "Sulit tapi lolos. Ketemu lagi {$next_int} hari." : "Bagus! Ketemu lagi {$next_int} hari.");
                if ($xp_gain <= 0) $msg .= '';
                if (!empty($nb)) $msg .= ' Badge: ' . implode(', ', $nb) . '!';
                if (!$is_ajax) set_flash('success', $msg);
            } else {
                $up = $conn->prepare("UPDATE reviews SET interval_day = 1, next_due = DATE_ADD(CURDATE(), INTERVAL 1 DAY), lapses = lapses + 1, ease_factor = ?, reps = 0 WHERE id = ? AND user_id = ?");
                $up->bind_param("dii", $new_ease, $rid, $user_id);
                $up->execute(); $up->close();
                $msg = 'Dicatat. Kita ulang besok agar nempel.';
                if (!$is_ajax) set_flash('info', $msg);
            }
            if ($is_ajax) {
                $deck_sql = $deck !== 'Semua' ? " AND skill = '" . $conn->real_escape_string($deck) . "'" : "";
                $c = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND next_due <= CURDATE()" . $deck_sql);
                $c->bind_param("i", $user_id); $c->execute();
                $remaining = (int)($c->get_result()->fetch_assoc()['c'] ?? 0);
                $c->close();
                $next_card = null;
                if ($remaining > 0) {
                    $nc = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses, ease_factor, reps, skill FROM reviews WHERE user_id = ? AND next_due <= CURDATE()" . $deck_sql . " ORDER BY next_due ASC, id ASC LIMIT 1");
                    $nc->bind_param("i", $user_id); $nc->execute();
                    $next_card = $nc->get_result()->fetch_assoc();
                    $nc->close();
                }
                $conn->close();
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success', 'result' => $grade, 'message' => $msg ?? 'Tersimpan.',
                    'xp_gain' => $xp_gain, 'new_badges' => $nb, 'next_interval' => $next_int,
                    'remaining' => $remaining, 'next' => $next_card,
                ]);
                exit();
            }
        }
    }
    redirect('review.php' . ($deck !== 'Semua' ? '?deck=' . urlencode($deck) : ''));
}

$deck_counts = [];
try {
    $s = $conn->query("SELECT skill, COUNT(*) n FROM reviews WHERE user_id = " . (int)$user_id . " AND next_due <= CURDATE() GROUP BY skill");
    if ($s) {
        foreach ($s->fetch_all(MYSQLI_ASSOC) as $r) $deck_counts[(string)($r['skill'] ?? 'General')] = (int)$r['n'];
        $s->free();
    }
} catch (Throwable $e) {}
$due_count = array_sum($deck_counts);
$overdue = 0; $upcoming = 0;
try {
    $s = $conn->prepare("SELECT (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due < CURDATE()) AS od, (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due > CURDATE() AND next_due <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS up");
    $s->bind_param("ii", $user_id, $user_id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc() ?: [];
    $s->close();
    $overdue = (int)($r['od'] ?? 0);
    $upcoming = (int)($r['up'] ?? 0);
} catch (Throwable $e) {}

if ($deck !== 'Semua') {
    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses, ease_factor, reps, skill FROM reviews WHERE user_id = ? AND skill = ? AND next_due <= CURDATE() ORDER BY next_due ASC, id ASC LIMIT 20");
    $stmt->bind_param("is", $user_id, $deck);
} else {
    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, title, detail, next_due, interval_day, done_count, lapses, ease_factor, reps, skill FROM reviews WHERE user_id = ? AND next_due <= CURDATE() ORDER BY next_due ASC, id ASC LIMIT 20");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$deck_due = count($due);
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
$labels = sm2_labels();
$skill_of_current = (string)($current['skill'] ?? 'General');
$page_title = 'Review Inbox';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" id="main" role="main">
    <div class="page-head">
        <div class="page-kicker" id="reviewKicker"><?= $due_count ?> perlu direview · <?= $overdue ?> terlambat · <?= $upcoming ?> minggu depan</div>
        <h1 class="page-title">Review Inbox</h1>
        <p class="page-desc">Nilai jujur tiap kartu. Lagi = besok, Sulit = segera, Bisa = sesuai jadwal, Mudah = lama.</p>
        <div class="page-actions review-back">
            <a href="index.php" class="page-actions-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>Dashboard</a>
        </div>
    </div>

    <div class="filter-pills mb-3" role="tablist" aria-label="Filter deck skill">
        <a class="filter-pill <?= $deck === 'Semua' ? 'active' : '' ?>" href="review.php" role="tab" <?= $deck === 'Semua' ? 'aria-selected="true"' : '' ?>>Semua (<?= $due_count ?>)</a>
        <?php foreach ($deck_names as $dn): $dc = (int)($deck_counts[$dn] ?? 0); if ($dc <= 0 && $dn !== $deck) continue; ?>
        <a class="filter-pill <?= $deck === $dn ? 'active' : '' ?>" href="review.php?deck=<?= urlencode($dn) ?>" role="tab" <?= $deck === $dn ? 'aria-selected="true"' : '' ?>><?= htmlspecialchars($dn) ?> (<?= $dc ?>)</a>
        <?php endforeach; ?>
    </div>

    <?php if (!$current): ?>
    <div class="empty-state card p-4 p-md-5">
        <div class="empty-state-icon"><i class="fas fa-check-double"></i></div>
        <h2 class="h5 fw-bold">Bersih! <?= $deck !== 'Semua' ? 'Deck ' . htmlspecialchars($deck) . ' ' : '' ?>tidak ada yang jatuh tempo.</h2>
        <p class="text-secondary small mb-3">Selesaikan quest atau tulis catatan — otomatis masuk antrean review besok.<?= $upcoming > 0 ? " {$upcoming} kartu menunggu minggu depan." : '' ?></p>
        <?php if ($deck !== 'Semua'): ?><a href="review.php" class="btn btn-cyber-outline btn-sm">Lihat semua deck</a><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row g-4 justify-content-center">
        <div class="col-lg-7">
            <article class="card p-4 review-card">
                <div class="review-progress">
                    <span id="reviewPos">Kartu 1 dari <?= $deck_due ?></span>
                    <div class="review-progress-bar" role="progressbar" aria-valuenow="1" aria-valuemin="0" aria-valuemax="<?= $deck_due ?>" aria-label="Posisi kartu review"><div id="reviewBar" style="width: <?= (int)round(100 / max(1, $deck_due)) ?>%;"></div></div>
                </div>
                <div class="review-meta">
                    <span class="question-status status-in_review" id="reviewSource"><?= htmlspecialchars($current['source']) ?></span>
                    <span class="skill-lv" id="reviewSkill"><?= htmlspecialchars($skill_of_current) ?></span>
                    <span class="review-due" id="reviewDue"><?= htmlspecialchars($due_label) ?> · interval <?= (int)$current['interval_day'] ?> hari · reps <?= (int)($current['reps'] ?? 0) ?></span>
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
                <form method="POST" action="review.php" class="review-actions review-grades">
                    <?= csrf_field() ?>
                    <input type="hidden" name="review_id" id="reviewIdInput" value="<?= (int)$current['id'] ?>">
                    <input type="hidden" name="deck" value="<?= htmlspecialchars($deck) ?>">
                    <?php foreach ($labels as $gv => $gl): ?>
                    <button type="submit" name="result" value="<?= $gv ?>" class="btn grade-<?= $gv ?>" title="<?= htmlspecialchars($gl['label']) ?> (<?= $gl['key'] ?>) · <?= htmlspecialchars($gl['hint']) ?>"><strong><?= htmlspecialchars($gl['label']) ?></strong><small><?= htmlspecialchars($gl['hint']) ?> · <?= $gl['key'] ?></small></button>
                    <?php endforeach; ?>
                </form>
                <p class="small text-muted mt-3 mb-0" id="reviewLeft">Sisa <?= $deck_due - 1 ?> kartu lagi setelah ini<?= $deck !== 'Semua' ? ' di deck ' . htmlspecialchars($deck) : '' ?>. Tombol 1–4 di keyboard.</p>
            </article>
        </div>
    </div>
    <script>
    document.addEventListener('keydown', function(e) {
        if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
        var map = { '1': 'again', '2': 'hard', '3': 'good', '4': 'easy' };
        var g = map[e.key];
        if (!g) return;
        var btn = document.querySelector('.review-grades button[value="' + g + '"]');
        if (btn) { e.preventDefault(); btn.click(); }
    });
    </script>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
