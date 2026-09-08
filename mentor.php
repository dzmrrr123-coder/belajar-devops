<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$track = user_track($conn, $uid);
$sig = \App\Domain\NextAction::signals($conn, $uid);
$due = (int)($sig['due_reviews'] ?? 0);
$tries = 0; $avg = 0; $gap = '';
try {
    $q = $conn->prepare("SELECT COUNT(*) c, COALESCE(AVG(score),0) a FROM incident_attempts WHERE user_id = ?");
    if ($q) { $q->bind_param("i", $uid); $q->execute(); $r = $q->get_result()->fetch_assoc() ?: []; $q->close(); $tries = (int)($r['c'] ?? 0); $avg = (int)($r['a'] ?? 0); }
    $g = $conn->prepare("SELECT skill, COUNT(*) c FROM errors WHERE user_id = ? GROUP BY skill ORDER BY c ASC LIMIT 1");
    if ($g) { $g->bind_param("i", $uid); $g->execute(); $gap = (string)($g->get_result()->fetch_assoc()['skill'] ?? ''); $g->close(); }
    $qd = $conn->prepare("SELECT COUNT(*) c FROM user_quests WHERE user_id = ?");
    if ($qd) { $qd->bind_param("i", $uid); $qd->execute(); $qdone = (int)($qd->get_result()->fetch_assoc()['c'] ?? 0); $qd->close(); }
    $qt = $conn->prepare("SELECT COUNT(*) c FROM quests WHERE user_id IS NULL AND (track = ? OR track = 'all')");
    if ($qt) { $qt->bind_param("s", $track); $qt->execute(); $qtotal = max(1, (int)($qt->get_result()->fetch_assoc()['c'] ?? 1)); $qt->close(); }
} catch (Throwable $e) { $qdone = 0; $qtotal = 1; }
$conn->close();
$recs = \App\Domain\Mentor::recommend(['track' => $track, 'due_reviews' => $due, 'incident_tries' => $tries, 'incident_avg' => $avg, 'skill_gap' => $gap, 'streak_broken' => !empty($sig['streak_broken']), 'quest_pct' => isset($qdone, $qtotal) ? ($qdone / $qtotal * 100) : 100]);
$page_title = 'AI Mentor';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Mentor otomatis · tanpa antre</div>
<h1 class="page-title">Langkah berikutnya untukmu</h1>
<p class="page-desc">Rekomendasi adaptif berdasarkan track <strong><?= htmlspecialchars(strtoupper($track)) ?></strong>, aktivitas harian, dan penguasaan kompetensi.</p></div>
<div class="row g-3">
<?php foreach ($recs as $r): ?>
<div class="col-md-4"><div class="card p-4 h-100"><i class="<?= htmlspecialchars($r['icon']) ?>"></i><h2 class="h5 mt-2"><?= htmlspecialchars($r['title']) ?></h2><p class="small text-muted"><?= htmlspecialchars($r['desc']) ?></p><a class="btn btn-cyber btn-sm w-100" href="<?= htmlspecialchars($r['href']) ?>"><?= htmlspecialchars($r['cta']) ?></a></div></div>
<?php endforeach; ?>
</div>
</main>
<?php require_once 'includes/footer.php'; ?>
