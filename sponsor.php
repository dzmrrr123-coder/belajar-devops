<?php
require_once 'config.php';
$conn = db_connect();
\App\Domain\Sponsor::seed($conn);
$sponsors = \App\Domain\Sponsor::all($conn);
$labs = \App\Domain\Lab\LabBank::all();
$conn->close();
$page_title = 'Sponsor Challenge';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">B2B · didukung industri</div>
<h1 class="page-title">Sponsor challenge</h1><p class="page-desc">Perusahaan mendukung lab praktik. Tertarik jadi sponsor? Ajukan via waitlist Tim.</p></div>
<div class="row g-3">
<?php foreach ($sponsors as $s): ?>
<div class="col-md-4"><div class="card p-4 h-100"><h2 class="h5"><?= htmlspecialchars($s['name']) ?></h2>
<?php $mine = array_filter($labs, fn($l) => !empty($l['sponsor']) && $l['sponsor'] === $s['name']); ?>
<p class="small text-muted"><?= count($mine) ?> lab didukung</p>
<?php foreach ($mine as $l): ?><p class="small mb-1"><a href="lab.php?slug=<?= urlencode($l['slug']) ?>"><?= htmlspecialchars($l['title']) ?></a></p><?php endforeach; ?>
<a class="btn btn-cyber-outline btn-sm w-100 mt-2" href="pricing.php">Jadi sponsor</a></div></div>
<?php endforeach; ?>
<?php if (!$sponsors): ?><div class="card p-4"><p class="small mb-0">Belum ada sponsor. Jadilah yang pertama.</p></div><?php endif; ?>
</div>
</main>
<?php require_once 'includes/footer.php'; ?>
