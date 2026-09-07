<?php
require_once 'config.php';
$conn = db_connect();
\App\Domain\Incident\Certificate::ensureTables($conn);
$code = mb_substr(trim($_GET['code'] ?? ''), 0, 16);
$row = null;
if ($code !== '') {
    $s = $conn->prepare("SELECT t.code, t.score, t.issued_at, u.username, c.title, c.skill, c.difficulty FROM certificates t JOIN users u ON u.id = t.user_id JOIN incident_challenges c ON c.id = t.challenge_id WHERE t.code = ?");
    if ($s) { $s->bind_param("s", $code); $s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close(); }
}
$conn->close();
$page_title = 'Verifikasi Sertifikat';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Verifiable evidence · bukan badge kosong</div>
<h1 class="page-title">Sertifikat <?= htmlspecialchars($code ?: 'DevQuest') ?></h1>
<p class="page-desc">Tampilkan challenge, score, dan tanggal. Jangan janjikan pekerjaan.</p></div>
<?php if ($row): ?>
<section class="card p-4">
<p class="mb-1"><strong><?= htmlspecialchars($row['username']) ?></strong> · <?= htmlspecialchars($row['title']) ?></p>
<p class="small text-muted mb-2"><?= htmlspecialchars($row['skill']) ?> · <?= htmlspecialchars($row['difficulty']) ?> · score <?= (int)$row['score'] ?>/100 · <?= date('d M Y', strtotime($row['issued_at'])) ?></p>
<p class="small mb-0">Kode <?= htmlspecialchars($row['code']) ?> valid. Bukti: <a href="u.php?u=<?= urlencode($row['username']) ?>">passport <?= htmlspecialchars($row['username']) ?></a>.</p>
</section>
<?php else: ?>
<section class="card p-4"><p class="small mb-2">Kode tidak ditemukan. Minta format <code>DQ-XXXXXX</code> dari halaman incident (score ≥70).</p><a href="incident.php" class="btn btn-cyber btn-sm">Ke Incident Simulator</a></section>
<?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
