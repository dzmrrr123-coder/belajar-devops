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
<div class="page-head mb-4">
    <div class="page-kicker eyebrow"><i class="<?= htmlspecialchars($trackInfo['icon'] ?? 'fas fa-flask') ?> me-1"></i> Jurusan <?= htmlspecialchars($trackInfo['name']) ?> · Lab Praktikum</div>
    <h1 class="page-title">Lab praktik kilat</h1>
    <p class="page-desc">Latihan interaktif sesuai kurikulum <?= htmlspecialchars($trackInfo['name']) ?>. Batas XP harian: +<?= LAB_DAILY_CAP ?> XP/hari.</p>
</div>

<div class="row g-4 align-items-stretch">
    <!-- Left Column: Terminal UI -->
    <div class="col-lg-7 d-flex flex-column">
        <?php if ($active): ?>
        <section class="card bg-dark text-light border-0 h-100 p-0 overflow-hidden shadow-sm" style="border-radius: 12px;">
            <div class="bg-black bg-opacity-50 p-2 border-bottom border-secondary border-opacity-25 d-flex align-items-center gap-2">
                <span class="rounded-circle bg-danger" style="width:12px; height:12px;"></span>
                <span class="rounded-circle bg-warning" style="width:12px; height:12px;"></span>
                <span class="rounded-circle bg-success" style="width:12px; height:12px;"></span>
                <span class="ms-2 small font-monospace text-secondary opacity-75">user@learntracker:~/$ <?= htmlspecialchars($active['slug']) ?></span>
                <div class="ms-auto d-flex gap-2">
                    <span class="badge bg-secondary bg-opacity-25 text-light"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($active['skill']) ?></span>
                    <span class="badge bg-warning bg-opacity-25 text-warning"><i class="fas fa-bolt me-1"></i>+<?= (int)$active['xp'] ?> XP</span>
                </div>
            </div>
            <div class="p-4 flex-grow-1 font-monospace small d-flex flex-column" style="line-height: 1.6;">
                <h2 class="text-info h5 fw-bold mb-3">> <?= htmlspecialchars($active['title']) ?></h2>
                <div class="text-light text-opacity-75 mb-3 fs-6" style="white-space: pre-wrap;"><?= htmlspecialchars($active['prompt']) ?></div>
                
                <?php if (!empty($active['code'])): ?>
                    <pre class="p-3 bg-black bg-opacity-50 rounded border border-secondary border-opacity-25 text-light mt-auto mb-0" style="font-size: 0.85rem;"><code><?= htmlspecialchars($active['code']) ?></code></pre>
                <?php endif; ?>
                
                <?php if (!empty($active['sponsor'])): ?>
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 text-secondary text-opacity-50 small">
                        <i class="fas fa-handshake me-1"></i> Didukung oleh <?= htmlspecialchars($active['sponsor']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <!-- Right Column: Control Panel & Grid -->
    <div class="col-lg-5 d-flex flex-column gap-3">
        <?php if ($result): ?>
        <section class="card p-3 border-<?= $result['ok'] ? 'success' : 'warning' ?>">
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
        <section class="card p-4 shadow-sm border-0 bg-body-tertiary">
            <h3 class="h6 fw-bold mb-3"><i class="fas fa-keyboard me-2"></i>Kontrol Misi</h3>
            <form method="POST" class="d-flex flex-column gap-3 m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>">
                <?php if (($active['type'] ?? 'mcq') === 'calc'): ?>
                    <div class="input-group">
                        <input name="answer" class="form-control" placeholder="Input output/angka..." inputmode="numeric" required autocomplete="off">
                        <button class="btn btn-cyber" type="submit">Execute</button>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($active['options'] as $i => $o): ?>
                            <label class="d-flex gap-3 align-items-center p-2 px-3 rounded border border-secondary-subtle bg-surface" style="cursor: pointer; transition: all 0.2s;" onmouseover="this.classList.add('border-primary')" onmouseout="this.classList.remove('border-primary')">
                                <input type="radio" name="answer" value="<?= $i ?>" required class="form-check-input mt-0">
                                <span class="small font-monospace"><?= htmlspecialchars($o) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-cyber w-100 mt-2" type="submit"><i class="fas fa-play me-2"></i>Execute Kueri</button>
                <?php endif; ?>
            </form>
        </section>
        <?php endif; ?>

        <section class="card p-4 shadow-sm border-0">
            <h3 class="h6 fw-bold mb-3"><i class="fas fa-layer-group me-2"></i>Lab Jurusan <?= htmlspecialchars($trackInfo['name']) ?> (<?= count($mine) ?>)</h3>
            <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                <?php foreach ($mine as $l): $isActive = ($active && $active['slug'] === $l['slug']); ?>
                    <a class="list-row <?= $isActive ? 'active bg-primary bg-opacity-10 border-primary' : '' ?>" href="lab.php?slug=<?= urlencode($l['slug']) ?>" style="padding: 10px 12px; text-decoration: none;">
                        <div class="list-main">
                            <p class="list-title <?= $isActive ? 'text-primary' : '' ?>"><?= htmlspecialchars($l['title']) ?></p>
                            <p class="list-meta"><?= htmlspecialchars($l['skill']) ?></p>
                        </div>
                        <?php if ($isActive): ?><i class="fas fa-chevron-right text-primary small"></i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($others)): ?>
            <div class="mt-3 pt-3 border-top">
                <details>
                    <summary class="small text-muted fw-bold" style="cursor: pointer;"><i class="fas fa-compass me-1"></i> Eksplorasi Jurusan Lain (<?= count($others) ?>)</summary>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <?php foreach ($others as $l): ?>
                            <a class="badge bg-secondary-subtle text-secondary text-decoration-none <?= ($active && $active['slug'] === $l['slug']) ? 'border border-primary' : '' ?>" href="lab.php?slug=<?= urlencode($l['slug']) ?>">
                                <?= htmlspecialchars(strtoupper($l['track'])) ?>: <?= htmlspecialchars($l['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

</main>
<?php require_once 'includes/footer.php'; ?>
