<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$track = user_track($conn, $uid);
$trackInfo = \App\Domain\Track\Tracks::all()[$track] ?? ['name' => strtoupper($track)];
$primaryFeature = \App\Domain\Track\Tracks::primaryFeature($track);

$card1_kicker = 'Praktik & Bukti';
$card1_num = 0;
$card1_unit = '';
$card1_desc = '';

if ($track === 'dkv') {
    $card1_kicker = 'Karya Desain';
    $kc = $conn->prepare("SELECT COUNT(*) c FROM submission_files WHERE owner_id = ?");
    if ($kc) { $kc->bind_param("i", $uid); $kc->execute(); $card1_num = (int)($kc->get_result()->fetch_assoc()['c'] ?? 0); $kc->close(); }
    $card1_unit = ' karya';
    $rubricAvg = \App\Domain\Dkv\Rubric::portfolioAvg($conn, $uid);
    $card1_desc = 'Nilai rubrik rata-rata: ' . ($rubricAvg['avg'] > 0 ? $rubricAvg['avg'] . '/5' : 'Belum dinilai');
} elseif ($track === 'rpl') {
    $card1_kicker = 'Playground & Coding';
    $pc = $conn->prepare("SELECT COUNT(*) c FROM xp_events WHERE user_id = ? AND ref_type = 'playground'");
    if ($pc) { $pc->bind_param("i", $uid); $pc->execute(); $card1_num = (int)($pc->get_result()->fetch_assoc()['c'] ?? 0); $pc->close(); }
    $card1_unit = ' challenge';
    $card1_desc = 'Eksekusi PHP, JS & SQL tervalidasi';
} elseif ($track === 'tkj') {
    $card1_kicker = 'Topologi & Network';
    $tc = $conn->prepare("SELECT COUNT(*) c FROM topo_saves WHERE user_id = ?");
    if ($tc) { $tc->bind_param("i", $uid); $tc->execute(); $card1_num = (int)($tc->get_result()->fetch_assoc()['c'] ?? 0); $tc->close(); }
    $card1_unit = ' desain';
    $card1_desc = 'Topologi & perhitungan subnet tersimpan';
} else {
    $card1_kicker = 'Incident';
    $s = $conn->prepare("SELECT COUNT(*) n, COALESCE(AVG(score),0) avg, COALESCE(MAX(score),0) best FROM incident_attempts WHERE user_id = ?");
    if ($s) { $s->bind_param("i", $uid); $s->execute(); $inc = $s->get_result()->fetch_assoc() ?: ['n' => 0, 'avg' => 0, 'best' => 0]; $s->close(); }
    $card1_num = (int)($inc['n'] ?? 0);
    $card1_unit = ' percobaan';
    $card1_desc = 'Rata-rata ' . (int)($inc['avg'] ?? 0) . ' · terbaik ' . (int)($inc['best'] ?? 0);
}

$rev = ['done7' => 0, 'due' => 0];
try {
    $s = $conn->prepare("SELECT (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due <= CURDATE()) AS due, (SELECT COUNT(*) FROM xp_events WHERE user_id = ? AND reason = 'review' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS done7");
    if ($s) { $s->bind_param("ii", $uid, $uid); $s->execute(); $rev = $s->get_result()->fetch_assoc() ?: $rev; $s->close(); }
} catch (Throwable $e) {}

$certs = \App\Domain\Incident\Certificate::forUser($conn, $uid);
$conn->close();

$page_title = 'Progress Outcome ' . $trackInfo['name'];
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head">
    <div class="page-kicker eyebrow">Jurusan <?= htmlspecialchars($trackInfo['name']) ?> · Outcome Nyata</div>
    <h1 class="page-title">Progress bermakna</h1>
    <p class="page-desc">Penguasaan kompetensi, retensi memori, dan bukti karya nyata untuk portofolio dan persiapan PKL.</p>
</div>
<div class="row g-3">
<div class="col-md-4"><div class="card p-4 h-100"><div class="page-kicker"><?= htmlspecialchars($card1_kicker) ?></div><div class="hero-num"><?= $card1_num ?><small><?= $card1_unit ?></small></div><p class="small text-muted mb-0"><?= htmlspecialchars($card1_desc) ?></p></div></div>
<div class="col-md-4"><div class="card p-4 h-100"><div class="page-kicker">Review retention</div><div class="hero-num"><?= (int)($rev['done7'] ?? 0) ?><small> /7 hari</small></div><p class="small text-muted mb-0"><?= (int)($rev['due'] ?? 0) ?> kartu jatuh tempo. <a href="review.php">Buka review</a>.</p></div></div>
<div class="col-md-4"><div class="card p-4 h-100"><div class="page-kicker"><?= $track === 'dkv' ? 'Bukti Karya' : 'Evidence Sertifikat' ?></div><div class="hero-num"><?= $track === 'dkv' ? $card1_num : count($certs) ?><small> <?= $track === 'dkv' ? 'karya' : 'sertifikat' ?></small></div><p class="small text-muted mb-0"><?= $track === 'dkv' ? 'Tersimpan di portofolio publik' : 'Tervalidasi anti-cheat' ?>.</p></div></div>
</div>
<div class="card p-4 mt-3">
    <h2 class="h6 fw-bold mb-2">Langkah berikutnya</h2>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= htmlspecialchars($primaryFeature['href']) ?>" class="btn btn-cyber btn-sm"><i class="<?= htmlspecialchars($primaryFeature['icon']) ?> me-1"></i><?= htmlspecialchars($primaryFeature['title']) ?></a>
        <a href="review.php" class="btn btn-cyber-outline btn-sm"><i class="fas fa-rotate-right me-1"></i>Review</a>
        <a href="export.php" class="btn btn-cyber-outline btn-sm"><i class="fas fa-file-export me-1"></i>Export portfolio</a>
        <a href="feedback.php" class="btn btn-cyber-outline btn-sm"><i class="fas fa-comment me-1"></i>Beri feedback</a>
    </div>
</div>
</main>
<?php require_once 'includes/footer.php'; ?>
