<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$track = user_track($conn, $uid);
define('LAB_DAILY_CAP', 30);
$result = null;
$slug = trim($_GET['slug'] ?? '');
$active = $slug !== '' ? \App\Domain\Lab\LabBank::find($slug) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit_hit('lab_submit', 20, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('lab.php'); }
    $slug = trim($_POST['slug'] ?? '');
    $lab = \App\Domain\Lab\LabBank::find($slug);
    if (!$lab) { set_flash('warning', 'Lab tidak ditemukan.'); redirect('lab.php'); }
    $input = mb_substr(trim((string)($_POST['answer'] ?? '')), 0, 50);
    $ok = \App\Domain\Lab\LabBank::grade($lab, $input);
    $gain = 0;
    if ($ok) {
        $cap = 0;
        $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'lab' AND amount > 0 AND created_at >= CURDATE()");
        if ($c) { $c->bind_param("i", $uid); $c->execute(); $cap = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close(); }
        $gain = capped_xp_gain((int)$lab['xp'], $cap, LAB_DAILY_CAP);
        if ($gain > 0) {
            award_xp($conn, $uid, $gain, 'lab', 'lab', crc32($lab['slug']) % 100000);
            \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$lab['skill']), 5, 'lab', 'lab', crc32($lab['slug']) % 100000);
        }
        check_and_unlock_badges($conn, $uid);
    }
    $result = ['lab' => $lab, 'ok' => $ok, 'gain' => $gain, 'input' => $input];
    $active = $lab;
}
$mine = \App\Domain\Lab\LabBank::forTrack($track);
$others = array_values(array_filter(\App\Domain\Lab\LabBank::all(), fn($l) => $l['track'] !== $track));
$conn->close();
$page_title = 'Lab Praktik';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Playground RPL · Simulator TKJ · Challenge DKV</div>
<h1 class="page-title">Lab praktik 5 menit</h1>
<p class="page-desc">Sesuai track <?= htmlspecialchars(strtoupper($track)) ?>. XP +<?= LAB_DAILY_CAP ?>/hari anti-farm.</p></div>
<?php if ($result): ?>
<section class="card p-4 mb-3"><div class="page-kicker"><?= $result['ok'] ? 'Benar' : 'Belum tepat' ?> <?= $result['gain'] > 0 ? '· +' . (int)$result['gain'] . ' XP' : '' ?></div>
<p class="small mb-0"><?= htmlspecialchars($result['lab']['explanation']) ?></p></section>
<?php endif; ?>
<?php if ($active): ?>
<section class="card p-4 mb-3"><h2 class="h5"><?= htmlspecialchars($active['title']) ?> · <?= htmlspecialchars($active['skill']) ?></h2>
<p class="small text-muted"><?= htmlspecialchars($active['prompt']) ?></p>
<?php if (!empty($active['code'])): ?><pre class="small p-2 bg-dark text-light rounded"><?= htmlspecialchars($active['code']) ?></pre><?php endif; ?>
<?php if (!empty($active['sponsor'])): ?><p class="small text-muted">Didukung <?= htmlspecialchars($active['sponsor']) ?></p><?php endif; ?>
<form method="POST" class="d-flex flex-column gap-2"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>">
<?php if (($active['type'] ?? 'mcq') === 'calc'): ?>
<input name="answer" class="form-control" placeholder="Jawaban angka…" inputmode="numeric" required>
<?php else: ?>
<?php foreach ($active['options'] as $i => $o): ?><label class="d-flex gap-2 align-items-center"><input type="radio" name="answer" value="<?= $i ?>" required><span class="small"><?= htmlspecialchars($o) ?></span></label><?php endforeach; ?>
<?php endif; ?>
<button class="btn btn-cyber btn-sm" type="submit">Kumpulkan</button></form></section>
<?php endif; ?>
<h2 class="h5">Untuk trackmu (<?= count($mine) ?>)</h2>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($mine as $l): ?><a class="btn btn-cyber-outline btn-sm" href="lab.php?slug=<?= urlencode($l['slug']) ?>"><?= htmlspecialchars($l['title']) ?></a><?php endforeach; ?></div>
<h2 class="h5">Track lain (<?= count($others) ?>)</h2>
<div class="d-flex flex-wrap gap-2"><?php foreach ($others as $l): ?><a class="btn btn-cyber-outline btn-sm" href="lab.php?slug=<?= urlencode($l['slug']) ?>"><?= htmlspecialchars($l['title']) ?> · <?= htmlspecialchars(strtoupper($l['track'])) ?></a><?php endforeach; ?></div>
</main>
<?php require_once 'includes/footer.php'; ?>
