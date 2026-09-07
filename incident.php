<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
\App\Domain\Incident\IncidentBank::ensureSeed($conn);
$s = $conn->prepare("SELECT id, is_pro, pro_until FROM users WHERE id = ?");
$s->bind_param("i", $uid); $s->execute();
$me = $s->get_result()->fetch_assoc() ?: []; $s->close();
$amPro = \App\Domain\Pro::canAccess($conn, $me, 'incident');
$result = null;
$hintShown = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'hint') {
        if (rate_limit_hit('incident_hint', 5, 3600)) { set_flash('warning', 'Hint dibatasi 5/jam. Coba lagi nanti.'); redirect('incident.php?slug=' . urlencode($_POST['slug'] ?? '')); }
        $cid = (int)($_POST['challenge_id'] ?? 0);
        $lvl = (int)($_POST['level'] ?? 1);
        $lvl = $lvl === 2 ? 2 : 1;
        $cnt = $conn->prepare("SELECT COUNT(*) c FROM incident_attempts WHERE user_id = ? AND challenge_id = ?");
        $cnt->bind_param("ii", $uid, $cid); $cnt->execute();
        $tries = (int)($cnt->get_result()->fetch_assoc()['c'] ?? 0); $cnt->close();
        if (!\App\Domain\Pro::hintAllowed($lvl - 1, $tries > 0)) { set_flash('warning', 'Coba jawab dulu 1x untuk buka hint lvl 2.'); redirect('incident.php?slug=' . urlencode($_POST['slug'] ?? '')); }
        $_SESSION['hint_' . $cid . '_' . $lvl] = true;
        \App\Analytics\Tracker::track($conn, $uid, \App\Analytics\Events::HINT_USED, ['challenge_id' => $cid, 'level' => $lvl]);
        redirect('incident.php?slug=' . urlencode($_POST['slug'] ?? ''));
    }
    if ($action === 'submit') {
        if (rate_limit_hit('incident_submit', 10, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('incident.php'); }
        $cid = (int)($_POST['challenge_id'] ?? 0);
        $st = $conn->prepare("SELECT * FROM incident_challenges WHERE id = ?");
        $st->bind_param("i", $cid); $st->execute(); $ch = $st->get_result()->fetch_assoc(); $st->close();
        if (!$ch) { set_flash('danger', 'Challenge tidak ditemukan.'); redirect('incident.php'); }
        if (!empty($ch['is_pro']) && !$amPro) { set_flash('warning', 'Challenge ini khusus Pro. Preview gratis, submit butuh Pro.'); redirect('pricing.php'); }
        $diag = (int)($_POST['diagnosis'] ?? -1);
        $fix = (int)($_POST['fix'] ?? -1);
        $start = (int)($_SESSION['incident_start_' . $cid] ?? time());
        $dur = max(5, time() - $start);
        $diagOk = $diag === (int)$ch['diagnosis_correct'];
        $fixOk = $fix === (int)$ch['fix_correct'];
        $sc = \App\Domain\Incident\IncidentBank::score($diagOk, $fixOk, $dur, (int)$ch['time_limit']);
        $xpFull = \App\Domain\Incident\IncidentBank::xpFor($sc['score'], (int)$ch['xp_reward']);
        $already = awarded_for_ref($conn, $uid, 'incident', $cid);
        $gain = max(0, $xpFull - $already);
        $ins = $conn->prepare("INSERT INTO incident_attempts (user_id, challenge_id, score, duration_sec, mistakes, diagnosis_ok, fix_ok) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $di = $diagOk ? 1 : 0; $fi = $fixOk ? 1 : 0;
        $ins->bind_param("iiiiiii", $uid, $cid, $sc['score'], $dur, $sc['mistakes'], $di, $fi);
        $ins->execute(); $ins->close();
        if ($gain > 0) {
            award_xp($conn, $uid, $gain, 'incident', 'incident', $cid);
            \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$ch['skill']), 10, 'incident', 'incident', $cid);
        }
        $nb = check_and_unlock_badges($conn, $uid);
        \App\Analytics\Tracker::track($conn, $uid, \App\Analytics\Events::INCIDENT_COMPLETED, ['challenge_id' => $cid, 'score' => $sc['score']]);
        $cert = \App\Domain\Incident\Certificate::issue($conn, $uid, $cid, $sc['score']);
        unset($_SESSION['incident_start_' . $cid]);
        $result = ['ch' => $ch, 'score' => $sc['score'], 'grade' => $sc['grade'], 'mistakes' => $sc['mistakes'], 'dur' => $dur, 'diagOk' => $diagOk, 'fixOk' => $fixOk, 'gain' => $gain, 'badges' => $nb, 'cert' => $cert];
    }
}
$list = [];
try {
    $q = $conn->prepare("SELECT c.*, (SELECT MAX(score) FROM incident_attempts a WHERE a.user_id = ? AND a.challenge_id = c.id) AS best, (SELECT COUNT(*) FROM incident_attempts a WHERE a.user_id = ? AND a.challenge_id = c.id) AS tries FROM incident_challenges c ORDER BY c.is_pro ASC, c.id ASC");
    if ($q) { $q->bind_param("ii", $uid, $uid); $q->execute(); $list = $q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close(); }
} catch (Throwable $e) {}
$slug = trim($_GET['slug'] ?? '');
$active = null;
foreach ($list as $c) if ($c['slug'] === $slug) $active = $c;
if ($active) {
    $_SESSION['incident_start_' . (int)$active['id']] = $_SESSION['incident_start_' . (int)$active['id']] ?? time();
    $dopts = json_decode($active['diagnosis_opts'] ?? '[]', true) ?: [];
    $fopts = json_decode($active['fix_opts'] ?? '[]', true) ?: [];
    $locked = !empty($active['is_pro']) && !$amPro;
    $hintShown = [1 => !empty($_SESSION['hint_' . (int)$active['id'] . '_1']), 2 => !empty($_SESSION['hint_' . (int)$active['id'] . '_2'])];
} else { $dopts = []; $fopts = []; $locked = false; }
$conn->close();
$page_title = 'Incident Simulator';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Premium challenge · bukti skill nyata</div>
<h1 class="page-title">Incident simulator</h1>
<p class="page-desc">Baca log, pilih diagnosis, pilih fix aman. Dinilai: score, waktu, kesalahan + feedback.<?= $amPro ? '' : ' Gratis: 2 lab. Pro: 14 lab + sertifikat.' ?></p></div>
<?php if ($result): $rc = $result['ch']; ?>
<section class="card p-4 mb-3" aria-label="Hasil">
<div class="page-kicker">Grade <?= htmlspecialchars($result['grade']) ?> · Score <?= (int)$result['score'] ?>/100</div>
<h2 class="h5 fw-bold mb-1"><?= $result['diagOk'] && $result['fixOk'] ? 'Incident pulih. Bagus.' : 'Belum pulih. Pelajari feedback.' ?></h2>
<p class="small text-muted mb-2"><?= (int)$result['dur'] ?> dtk · <?= (int)$result['mistakes'] ?> kesalahan · Skill: <?= htmlspecialchars($rc['skill']) ?> · +<?= (int)$result['gain'] ?> XP<?= !empty($result['badges']) ? ' · Badge: ' . htmlspecialchars(implode(', ', $result['badges'])) : '' ?></p>
<div class="code-solution mb-2"><?= nl2br(htmlspecialchars($result['diagOk'] && $result['fixOk'] ? $rc['feedback_ok'] : $rc['feedback_fail'])) ?></div>
<?php if (!empty($rc['explanation'])): ?><p class="small mb-2"><strong>Penjelasan:</strong> <?= htmlspecialchars($rc['explanation']) ?></p><?php endif; ?>
<p class="small mb-2">Diagnosis: <?= $result['diagOk'] ? 'benar' : 'kurang tepat' ?> · Fix: <?= $result['fixOk'] ? 'aman' : 'berisiko' ?></p>
<?php if (!empty($result['cert'])): ?><p class="small mb-2"><strong>Sertifikat:</strong> <a href="certificate.php?code=<?= urlencode($result['cert']) ?>"><?= htmlspecialchars($result['cert']) ?></a> · score ≥70, bisa dibagikan ke recruiter.</p><?php elseif ($result['score'] < 70): ?><p class="small text-muted mb-2">Sertifikat butuh score ≥70. Ulangi untuk perbaiki skor.</p><?php endif; ?>
<div class="d-flex gap-2 flex-wrap"><a href="incident.php" class="btn btn-cyber btn-sm">Challenge lain</a><a href="u.php?u=<?= urlencode($_SESSION['username'] ?? '') ?>" class="btn btn-cyber-outline btn-sm">Lihat di passport</a><?php if (!$amPro): ?><a href="pricing.php" class="btn btn-cyber-outline btn-sm">Jadi Pro</a><?php endif; ?></div>
</section>
<?php endif; ?>
<?php if ($active): ?>
<section class="card p-4 mb-3">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1"><strong><?= htmlspecialchars($active['title']) ?></strong><span class="small text-muted"><?= htmlspecialchars($active['skill']) ?> · <?= htmlspecialchars($active['difficulty']) ?> · ~<?= (int)($active['est_minutes'] ?? 10) ?> mnt · +<?= (int)$active['xp_reward'] ?> XP<?= !empty($active['is_pro']) ? ' · PRO' : '' ?></span></div>
<?php if (!empty($active['objective'])): ?><p class="small mb-1"><strong>Misi:</strong> <?= htmlspecialchars($active['objective']) ?></p><?php endif; ?>
<p class="small mb-2"><?= htmlspecialchars($active['story']) ?></p>
<div class="code-solution mb-3"><?= nl2br(htmlspecialchars($active['log_text'])) ?></div>
<?php if ($locked): ?>
<p class="small text-warning mb-2">Preview gratis. Submit + nilai + sertifikat khusus Pro — ini yang dijual, bukan XP.</p>
<a href="pricing.php" class="btn btn-cyber btn-sm">Buka akses Pro</a> <a href="incident.php" class="btn btn-cyber-outline btn-sm">Kembali</a>
<?php else: ?>
<div class="d-flex gap-2 flex-wrap mb-3">
<form method="POST" action="incident.php?slug=<?= urlencode($active['slug']) ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="hint"><input type="hidden" name="challenge_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>"><input type="hidden" name="level" value="1"><button class="btn btn-cyber-outline btn-sm" type="submit">Hint 1</button></form>
<form method="POST" action="incident.php?slug=<?= urlencode($active['slug']) ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="hint"><input type="hidden" name="challenge_id" value="<?= (int)$active['id'] ?>"><input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>"><input type="hidden" name="level" value="2"><button class="btn btn-cyber-outline btn-sm" type="submit">Hint 2 (butuh 1x coba)</button></form>
</div>
<?php if (!empty($hintShown[1])): ?><p class="small mb-1"><strong>Hint 1:</strong> <?= htmlspecialchars($active['hint_lvl1'] ?? '') ?></p><?php endif; ?>
<?php if (!empty($hintShown[2])): ?><p class="small mb-2"><strong>Hint 2:</strong> <?= htmlspecialchars($active['hint_lvl2'] ?? '') ?></p><?php endif; ?>
<form method="POST" action="incident.php?slug=<?= urlencode($active['slug']) ?>">
<?= csrf_field() ?><input type="hidden" name="action" value="submit"><input type="hidden" name="challenge_id" value="<?= (int)$active['id'] ?>">
<fieldset class="mb-3"><legend class="h6 fw-bold"><?= htmlspecialchars($active['diagnosis_q']) ?></legend>
<?php foreach ($dopts as $i => $o): ?><label class="wiz-opt d-block mb-1"><input type="radio" name="diagnosis" value="<?= $i ?>" required> <span><?= htmlspecialchars($o) ?></span></label><?php endforeach; ?></fieldset>
<fieldset class="mb-3"><legend class="h6 fw-bold"><?= htmlspecialchars($active['fix_q']) ?></legend>
<?php foreach ($fopts as $i => $o): ?><label class="wiz-opt d-block mb-1"><input type="radio" name="fix" value="<?= $i ?>" required> <span><?= htmlspecialchars($o) ?></span></label><?php endforeach; ?></fieldset>
<button class="btn btn-cyber" type="submit">Submit diagnosis & fix</button> <a href="incident.php" class="page-actions-link">Batal</a>
</form>
<?php endif; ?>
</section>
<?php else: ?>
<div class="row g-3">
<?php foreach ($list as $c): $done = $c['best'] !== null; ?>
<div class="col-md-6"><div class="card p-4 h-100">
<div class="page-kicker"><?= htmlspecialchars($c['skill']) ?> · <?= htmlspecialchars($c['difficulty']) ?><?= !empty($c['is_pro']) ? ' · PRO' : ' · GRATIS' ?></div>
<h2 class="h6 fw-bold"><?= htmlspecialchars($c['title']) ?></h2>
<?php if (!empty($c['objective'])): ?><p class="small mb-1"><?= htmlspecialchars($c['objective']) ?></p><?php endif; ?>
<p class="small text-muted mb-2"><?= htmlspecialchars(mb_strimwidth($c['story'], 0, 110, '...')) ?></p>
<p class="small mb-2"><?= $done ? 'Terbaik: <strong>' . (int)$c['best'] . '</strong> · ' . (int)$c['tries'] . 'x coba' : 'Belum dicoba' ?> · ~<?= (int)($c['est_minutes'] ?? 10) ?> mnt · +<?= (int)$c['xp_reward'] ?> XP</p>
<a href="incident.php?slug=<?= urlencode($c['slug']) ?>" class="btn <?= $done ? 'btn-cyber-outline' : 'btn-cyber' ?> btn-sm w-100"><?= $done ? 'Ulangi / perbaiki skor' : (!empty($c['is_pro']) && !$amPro ? 'Preview + Pro' : 'Mulai incident') ?></a>
</div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
