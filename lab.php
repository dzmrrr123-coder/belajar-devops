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
if (!$active && !empty($mine)) {
    $active = $mine[0];
}
$conn->close();
$trackInfo = \App\Domain\Track\Tracks::all()[$track] ?? ['name' => strtoupper($track)];
$page_title = 'Lab Praktik ' . $trackInfo['name'];
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head">
    <div class="page-kicker eyebrow"><i class="<?= htmlspecialchars($trackInfo['icon'] ?? 'fas fa-flask') ?> me-1"></i> Jurusan <?= htmlspecialchars($trackInfo['name']) ?> · Lab Praktikum 5 Menit</div>
    <h1 class="page-title">Lab praktik kilat</h1>
    <p class="page-desc">Latihan interaktif sesuai kurikulum <?= htmlspecialchars($trackInfo['name']) ?>. Batas XP harian: +<?= LAB_DAILY_CAP ?> XP/hari.</p>
</div>

<?php if ($result): ?>
<section class="card p-4 mb-3 border-<?= $result['ok'] ? 'success' : 'warning' ?>">
    <div class="d-flex align-items-center gap-2 mb-1">
        <i class="fas <?= $result['ok'] ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> fs-5"></i>
        <strong class="<?= $result['ok'] ? 'text-success' : 'text-warning' ?>"><?= $result['ok'] ? 'Jawaban Benar!' : 'Belum Tepat' ?></strong>
        <?php if ($result['gain'] > 0): ?>
            <span class="badge bg-success-subtle text-success ms-auto">+<?= (int)$result['gain'] ?> XP</span>
        <?php endif; ?>
    </div>
    <p class="small text-muted mb-0"><?= htmlspecialchars($result['lab']['explanation']) ?></p>
</section>
<?php endif; ?>

<?php if ($active): ?>
<section class="card p-4 mb-4 shadow-sm">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <span class="badge bg-cyber-subtle text-cyber px-2 py-1"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($active['skill']) ?></span>
        <span class="small text-muted"><i class="fas fa-bolt text-warning me-1"></i>+<?= (int)$active['xp'] ?> XP</span>
    </div>
    <h2 class="h5 fw-bold mb-2"><?= htmlspecialchars($active['title']) ?></h2>
    <p class="text-secondary small mb-3"><?= htmlspecialchars($active['prompt']) ?></p>

    <?php if (!empty($active['code'])): ?>
        <pre class="p-3 bg-dark text-light rounded font-monospace small mb-3"><code><?= htmlspecialchars($active['code']) ?></code></pre>
    <?php endif; ?>

    <?php if (!empty($active['sponsor'])): ?>
        <p class="small text-muted mb-3"><i class="fas fa-handshake me-1"></i>Didukung oleh <?= htmlspecialchars($active['sponsor']) ?></p>
    <?php endif; ?>

    <form method="POST" class="d-flex flex-column gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>">
        <?php if (($active['type'] ?? 'mcq') === 'calc'): ?>
            <div class="input-group">
                <input name="answer" class="form-control" placeholder="Tuliskan angka jawaban..." inputmode="numeric" required autocomplete="off">
                <button class="btn btn-cyber" type="submit">Kumpulkan</button>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($active['options'] as $i => $o): ?>
                    <label class="d-flex gap-3 align-items-center p-2 rounded border border-secondary-subtle bg-body-tertiary" style="cursor: pointer;">
                        <input type="radio" name="answer" value="<?= $i ?>" required class="form-check-input mt-0">
                        <span class="small"><?= htmlspecialchars($o) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-cyber btn-sm align-self-start mt-2 px-4" type="submit">Kumpulkan Jawaban</button>
        <?php endif; ?>
    </form>
</section>
<?php endif; ?>

<div class="mb-4">
    <h2 class="h6 fw-bold text-uppercase text-secondary tracking-wider mb-2">Daftar Lab Jurusan <?= htmlspecialchars($trackInfo['name']) ?> (<?= count($mine) ?>)</h2>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($mine as $l): ?>
            <a class="btn btn-cyber-outline btn-sm <?= ($active && $active['slug'] === $l['slug']) ? 'active' : '' ?>" href="lab.php?slug=<?= urlencode($l['slug']) ?>">
                <?= htmlspecialchars($l['title']) ?> <span class="text-muted small">· <?= htmlspecialchars($l['skill']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($others)): ?>
<details class="card p-3 bg-body-tertiary border-0">
    <summary class="small text-muted fw-bold" style="cursor: pointer;">
        <i class="fas fa-layer-group me-1"></i> Eksplorasi Lab Jurusan Lain (<?= count($others) ?>)
    </summary>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php foreach ($others as $l): ?>
            <a class="btn btn-outline-secondary btn-sm py-1 px-2 small <?= ($active && $active['slug'] === $l['slug']) ? 'active' : '' ?>" href="lab.php?slug=<?= urlencode($l['slug']) ?>">
                <?= htmlspecialchars($l['title']) ?> <span class="badge bg-secondary-subtle text-secondary ms-1"><?= htmlspecialchars(strtoupper($l['track'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

</main>
<?php require_once 'includes/footer.php'; ?>
