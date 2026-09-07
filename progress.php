<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$inc = ['n' => 0, 'avg' => 0, 'best' => 0];
try {
    $s = $conn->prepare("SELECT COUNT(*) n, COALESCE(AVG(score),0) avg, COALESCE(MAX(score),0) best FROM incident_attempts WHERE user_id = ?");
    if ($s) { $s->bind_param("i", $uid); $s->execute(); $inc = $s->get_result()->fetch_assoc() ?: $inc; $s->close(); }
} catch (Throwable $e) {}
$rev = ['done7' => 0, 'due' => 0];
try {
    $s = $conn->prepare("SELECT (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due <= CURDATE()) AS due, (SELECT COUNT(*) FROM xp_events WHERE user_id = ? AND reason = 'review' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS done7");
    if ($s) { $s->bind_param("ii", $uid, $uid); $s->execute(); $rev = $s->get_result()->fetch_assoc() ?: $rev; $s->close(); }
} catch (Throwable $e) {}
$hint7 = 0;
try {
    $s = $conn->prepare("SELECT COUNT(*) c FROM analytics_events WHERE user_id = ? AND event = 'hint_used' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($s) { $s->bind_param("i", $uid); $s->execute(); $hint7 = (int)($s->get_result()->fetch_assoc()['c'] ?? 0); $s->close(); }
} catch (Throwable $e) {}
$certs = \App\Domain\Incident\Certificate::forUser($conn, $uid);
$conn->close();
$page_title = 'Progress Outcome';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Outcome · bukan sekadar XP</div>
<h1 class="page-title">Progress bermakna</h1>
<p class="page-desc">Accuracy, retention, dan evidence — North Star: verified progress per learner.</p></div>
<div class="row g-3">
<div class="col-md-4"><div class="card p-4"><div class="page-kicker">Incident</div><div class="hero-num"><?= (int)$inc['n'] ?><small> percobaan</small></div><p class="small text-muted mb-0">Rata-rata <?= (int)$inc['avg'] ?> · terbaik <?= (int)$inc['best'] ?>.</p></div></div>
<div class="col-md-4"><div class="card p-4"><div class="page-kicker">Review retention</div><div class="hero-num"><?= (int)($rev['done7'] ?? 0) ?><small> /7 hari</small></div><p class="small text-muted mb-0"><?= (int)($rev['due'] ?? 0) ?> jatuh tempo sekarang. <a href="review.php">Kerjakan</a>.</p></div></div>
<div class="col-md-4"><div class="card p-4"><div class="page-kicker">Evidence</div><div class="hero-num"><?= count($certs) ?><small> sertifikat</small></div><p class="small text-muted mb-0"><?= $hint7 ?> hint dipakai 7 hari terakhir.</p></div></div>
</div>
<div class="card p-4 mt-3"><h2 class="h6 fw-bold mb-2">Langkah berikutnya</h2><div class="d-flex gap-2 flex-wrap"><a href="incident.php" class="btn btn-cyber btn-sm">Incident lab</a><a href="review.php" class="btn btn-cyber-outline btn-sm">Review</a><a href="export.php" class="btn btn-cyber-outline btn-sm">Export portfolio</a><a href="feedback.php" class="btn btn-cyber-outline btn-sm">Beri feedback</a></div></div>
</main>
<?php require_once 'includes/footer.php'; ?>
