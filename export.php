<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$format = $_GET['format'] ?? 'print';
$stmt = $conn->prepare("SELECT username, xp, streak, created_at, is_pro, pro_until, pro_plan FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();
$pro = is_pro($user);
$verify_code = 'LT-' . strtoupper(substr(hash('sha256', $user_id . '|' . $user['username'] . '|' . $user['created_at']), 0, 10));
$level = calculate_level($user['xp']);
$rank = get_user_rank($level);
$owned = user_badges($conn, $user_id);
$defs = badge_defs();
$s = $conn->prepare("SELECT q.title, q.week, uq.completed_at FROM user_quests uq JOIN quests q ON q.id=uq.quest_id WHERE uq.user_id=? ORDER BY q.week, q.id");
$s->bind_param("i", $user_id); $s->execute();
$done = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
$s = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM(duration_minutes),0) m FROM pomodoro_sessions WHERE user_id=?");
$s->bind_param("i", $user_id); $s->execute();
$pomo = $s->get_result()->fetch_assoc(); $s->close();
$s = $conn->prepare("SELECT COUNT(*) c FROM errors WHERE user_id=?");
$s->bind_param("i", $user_id); $s->execute();
$notes = (int)($s->get_result()->fetch_assoc()['c'] ?? 0); $s->close();
$incidents = [];
try {
    $s = $conn->prepare("SELECT c.title, c.skill, c.difficulty, MAX(a.score) best, COUNT(a.id) tries FROM incident_attempts a JOIN incident_challenges c ON c.id = a.challenge_id WHERE a.user_id = ? GROUP BY c.id, c.title, c.skill, c.difficulty ORDER BY best DESC");
    if ($s) { $s->bind_param("i", $user_id); $s->execute(); $incidents = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close(); }
} catch (Throwable $e) {}
$certs = \App\Domain\Incident\Certificate::forUser($conn, $user_id);
if ($format === 'json') {
    $dump = ['user' => $user, 'level' => $level, 'rank' => $rank, 'badges' => array_keys($owned), 'quests_done' => $done, 'incidents' => $incidents, 'certificates' => $certs, 'pomodoro' => $pomo, 'notes_count' => $notes, 'exported_at' => date('c')];
    foreach (['errors' => 'SELECT category, error_message, solution, reference_link, created_at FROM errors WHERE user_id=? ORDER BY created_at DESC', 'questions' => 'SELECT title, description, topic, status, priority, answer, created_at FROM questions WHERE user_id=? ORDER BY created_at DESC', 'pomodoro_sessions' => 'SELECT duration_minutes, mode, focus_note, completed_at FROM pomodoro_sessions WHERE user_id=? ORDER BY completed_at DESC LIMIT 500', 'reviews' => 'SELECT source, source_id, title, next_due, interval_day, done_count FROM reviews WHERE user_id=? ORDER BY next_due', 'missions' => 'SELECT mission_date, mission_key, claimed_at FROM daily_missions WHERE user_id=? ORDER BY mission_date DESC LIMIT 90'] as $k => $sql) {
        $s = $conn->prepare($sql);
        $s->bind_param("i", $user_id); $s->execute();
        $dump[$k] = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    }
    $conn->close();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="data-' . preg_replace('/[^a-z0-9_]/i', '', $user['username']) . '.json"');
    echo json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}
if ($format === 'md') {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="portfolio-' . preg_replace('/[^a-z0-9_]/i', '', $user['username']) . '.md"');
    echo "# Portofolio Belajar — " . $user['username'] . "\n\n";
    echo "- Level $level ($rank) · {$user['xp']} XP · {$user['streak']} streak\n";
    echo "- Quest selesai: " . count($done) . " · Fokus: " . (int)($pomo['c'] ?? 0) . " sesi (" . (int)($pomo['m'] ?? 0) . " mnt) · Catatan: $notes\n";
    echo "- Badge: " . implode(', ', array_map(fn($s) => $defs[$s]['name'] ?? $s, array_keys($owned))) . "\n\n";
    echo "## Quest selesai\n";
    foreach ($done as $d) echo "- [x] M" . (int)$d['week'] . " " . $d['title'] . "\n";
    echo "\n## Verified incidents\n";
    foreach ($incidents as $in) echo "- " . $in['title'] . " (" . $in['skill'] . ") · " . (int)$in['best'] . "/100\n";
    echo "\n## Sertifikat\n";
    foreach ($certs as $ct) echo "- " . $ct['code'] . " · " . $ct['title'] . " · " . (int)$ct['score'] . "/100 · " . $ct['issued_at'] . "\n";
    $conn->close();
    exit();
}
$conn->close();
$page_title = 'Export Portofolio';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head no-print">
        <div class="page-kicker">1 halaman · siap print/PDF</div>
        <h1 class="page-title">Portofolio <?= htmlspecialchars($user['username']) ?></h1>
        <div class="page-actions">
            <a href="export.php?format=md" class="btn btn-cyber-outline btn-sm">Unduh Markdown</a>
            <a href="export.php?format=json" class="btn btn-cyber-outline btn-sm">Unduh Data (JSON)</a>
            <button class="btn btn-cyber btn-sm" onclick="window.print()">Print / PDF</button>
        </div>
    </div>
    <article class="card p-4 print-sheet">
        <h2 class="h4 fw-bold mb-1"><?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($rank) ?></h2>
        <p class="text-secondary small">Level <?= $level ?> · <?= (int)$user['xp'] ?> XP · <?= (int)$user['streak'] ?> streak · <?= count($done) ?> quest · <?= (int)($pomo['c'] ?? 0) ?> sesi fokus · <?= $notes ?> catatan · <?= count($owned) ?> badge</p>
        <h3 class="h6 fw-bold mt-3">Quest selesai (<?= count($done) ?>)</h3>
        <ul class="small"><?php foreach (array_slice($done, 0, 20) as $d): ?><li>M<?= (int)$d['week'] ?> · <?= htmlspecialchars($d['title']) ?></li><?php endforeach; ?></ul>
        <?php if ($incidents): ?>
        <h3 class="h6 fw-bold mt-3">Verified incidents (<?= count($incidents) ?>)</h3>
        <ul class="small"><?php foreach ($incidents as $in): ?><li><?= htmlspecialchars($in['title']) ?> · <?= (int)$in['best'] ?>/100</li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($certs): ?>
        <h3 class="h6 fw-bold mt-3">Sertifikat</h3>
        <ul class="small"><?php foreach ($certs as $ct): ?><li><?= htmlspecialchars($ct['code']) ?> · <?= htmlspecialchars($ct['title']) ?> · <?= (int)$ct['score'] ?>/100</li><?php endforeach; ?></ul>
        <?php endif; ?>
        <h3 class="h6 fw-bold mt-3">Badge</h3>
        <p class="small text-secondary"><?= htmlspecialchars(implode(' · ', array_map(fn($s) => $defs[$s]['name'] ?? $s, array_keys($owned))) ?: '—') ?></p>
        <?php if ($pro): ?>
        <div class="alert alert-success mt-3 mb-0 small">Terverifikasi Pro · <?= htmlspecialchars($verify_code) ?> · <?= htmlspecialchars($user['username']) ?> · Level <?= $level ?> (<?= htmlspecialchars($rank) ?>)</div>
        <?php else: ?>
        <p class="small mt-3 mb-0">Butuh sertifikat verifikasi? <a href="pricing.php">Upgrade ke Pro</a> · kode kamu: <?= htmlspecialchars($verify_code) ?></p>
        <?php endif; ?>
    </article>
</main>
<style>@media print { .lt-navbar, .mobile-tabbar, footer, .no-print, .toast-container { display: none !important; } body { background: #fff; } .print-sheet { border: 1px solid #ccc; } }</style>
<?php require_once 'includes/footer.php'; ?>
