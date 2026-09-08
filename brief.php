<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
enforce_track_access($conn, $uid, ['dkv'], 'Brief Kreatif & Portofolio DKV');
$track = user_track($conn, $uid);
$conn->close();
$briefs = \App\Domain\Dkv\Brief::all();
$page_title = 'Brief DKV';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">DKV · brief siap kerjakan</div>
<h1 class="page-title">Brief kreatif</h1>
<p class="page-desc">Pilih 1 brief, upload karya di Roadmap, minta critique guru di Kelas.</p></div>
<div class="row g-3">
<?php foreach ($briefs as $b): ?>
<div class="col-md-4"><div class="card p-4 h-100"><div class="page-kicker">Minggu <?= (int)$b['week'] ?></div><h2 class="h5"><?= htmlspecialchars($b['title']) ?></h2><p class="small text-muted"><?= htmlspecialchars($b['goal']) ?></p><ul class="small"><?php foreach ($b['constraints'] as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?></ul><p class="small mb-2">Hasil: <?= htmlspecialchars($b['deliverable']) ?></p><p class="small text-muted mb-3">Tip: <?= htmlspecialchars($b['tip']) ?></p><a class="btn btn-cyber-outline btn-sm w-100" href="quests.php">Kerjakan di Roadmap</a></div></div>
<?php endforeach; ?>
</div>
</main>
<?php require_once 'includes/footer.php'; ?>
