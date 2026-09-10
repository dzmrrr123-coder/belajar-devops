<?php
// Alat: Incident Simulator (DevOps/TKJ)
\App\Domain\Incident\IncidentBank::ensureSeed($conn);
$s = $conn->prepare("SELECT id, is_pro, pro_until FROM users WHERE id = ?");
$s->bind_param("i", $uid); $s->execute();
$inc_me = $s->get_result()->fetch_assoc() ?: []; $s->close();
$inc_pro = \App\Domain\Pro::canAccess($conn, $inc_me, 'incident');
$inc_result = $_SESSION['lab_inc_result'] ?? null;
unset($_SESSION['lab_inc_result']);
$inc_list = [];
try {
    $q = $conn->prepare("SELECT c.*, (SELECT MAX(score) FROM incident_attempts a WHERE a.user_id = ? AND a.challenge_id = c.id) AS best, (SELECT COUNT(*) FROM incident_attempts a WHERE a.user_id = ? AND a.challenge_id = c.id) AS tries FROM incident_challenges c ORDER BY c.is_pro ASC, c.id ASC");
    if ($q) { $q->bind_param("ii", $uid, $uid); $q->execute(); $inc_list = $q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close(); }
} catch (Throwable $e) {}
$inc_slug = trim($_GET['inc_slug'] ?? '');
$inc_active = null;
foreach ($inc_list as $c) if ($c['slug'] === $inc_slug) $inc_active = $c;
if ($inc_active) {
    $_SESSION['incident_start_' . (int)$inc_active['id']] = $_SESSION['incident_start_' . (int)$inc_active['id']] ?? time();
    $inc_dopts = json_decode($inc_active['diagnosis_opts'] ?? '[]', true) ?: [];
    $inc_fopts = json_decode($inc_active['fix_opts'] ?? '[]', true) ?: [];
    $inc_locked = !empty($inc_active['is_pro']) && !$inc_pro;
    $inc_hint = [1 => !empty($_SESSION['hint_' . (int)$inc_active['id'] . '_1']), 2 => !empty($_SESSION['hint_' . (int)$inc_active['id'] . '_2'])];
} else { $inc_dopts = []; $inc_fopts = []; $inc_locked = false; $inc_hint = []; }
$inc_self = lab_url('praktik', 'incident') . '&inc_slug=' . urlencode($inc_active['slug'] ?? '');
?>
<p class="page-desc">Baca log, pilih diagnosis, pilih fix aman.<?= $inc_pro ? '' : ' Gratis: 2 lab. Pro: 14 lab + sertifikat.' ?></p>
<?php if ($inc_result): $rc = $inc_result['ch']; ?>
<section class="card p-4 mb-3" aria-label="Hasil">
<div class="page-kicker">Grade <?= htmlspecialchars($inc_result['grade']) ?> · Score <?= (int)$inc_result['score'] ?>/100</div>
<h2 class="h5 fw-bold mb-1"><?= $inc_result['diagOk'] && $inc_result['fixOk'] ? 'Incident pulih. Bagus.' : 'Belum pulih. Pelajari feedback.' ?></h2>
<p class="small text-muted mb-2"><?= (int)$inc_result['dur'] ?> dtk · <?= (int)$inc_result['mistakes'] ?> kesalahan · Skill: <?= htmlspecialchars($rc['skill']) ?> · +<?= (int)$inc_result['gain'] ?> XP<?= !empty($inc_result['badges']) ? ' · Badge: ' . htmlspecialchars(implode(', ', $inc_result['badges'])) : '' ?></p>
<div class="code-solution mb-2"><?= nl2br(htmlspecialchars($inc_result['diagOk'] && $inc_result['fixOk'] ? $rc['feedback_ok'] : $rc['feedback_fail'])) ?></div>
<?php if (!empty($rc['explanation'])): ?><p class="small mb-2"><strong>Penjelasan:</strong> <?= htmlspecialchars($rc['explanation']) ?></p><?php endif; ?>
<p class="small mb-2">Diagnosis: <?= $inc_result['diagOk'] ? 'benar' : 'kurang tepat' ?> · Fix: <?= $inc_result['fixOk'] ? 'aman' : 'berisiko' ?></p>
<?php if (!empty($inc_result['cert'])): ?><p class="small mb-2"><strong>Sertifikat:</strong> <a href="profile.php#trophies"><?= htmlspecialchars($inc_result['cert']) ?></a> · score ≥70, bisa dibagikan ke recruiter.</p><?php elseif ($inc_result['score'] < 70): ?><p class="small text-muted mb-2">Sertifikat butuh score ≥70. Ulangi untuk perbaiki skor.</p><?php endif; ?>
<div class="d-flex gap-2 flex-wrap"><a href="<?= lab_url('praktik', 'incident') ?>" class="btn btn-cyber btn-sm">Challenge lain</a><a href="u.php?u=<?= urlencode($_SESSION['username'] ?? '') ?>" class="btn btn-cyber-outline btn-sm">Lihat di passport</a><?php if (!$inc_pro): ?><a href="pricing.php" class="btn btn-cyber-outline btn-sm">Jadi Pro</a><?php endif; ?></div>
</section>
<?php endif; ?>
<?php if ($inc_active): ?>
<section class="card p-4 mb-3">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1"><strong><?= htmlspecialchars($inc_active['title']) ?></strong><span class="small text-muted"><?= htmlspecialchars($inc_active['skill']) ?> · <?= htmlspecialchars($inc_active['difficulty']) ?> · ~<?= (int)($inc_active['est_minutes'] ?? 10) ?> mnt · +<?= (int)$inc_active['xp_reward'] ?> XP<?= !empty($inc_active['is_pro']) ? ' · PRO' : '' ?></span></div>
<?php if (!empty($inc_active['objective'])): ?><p class="small mb-1"><strong>Misi:</strong> <?= htmlspecialchars($inc_active['objective']) ?></p><?php endif; ?>
<p class="small mb-2"><?= htmlspecialchars($inc_active['story']) ?></p>
<div class="code-solution mb-3"><?= nl2br(htmlspecialchars($inc_active['log_text'])) ?></div>
<?php if ($inc_locked): ?>
<p class="small text-warning mb-2">Preview gratis. Submit + nilai + sertifikat khusus Pro — ini yang dijual, bukan XP.</p>
<a href="pricing.php" class="btn btn-cyber btn-sm">Buka akses Pro</a> <a href="<?= lab_url('praktik', 'incident') ?>" class="btn btn-cyber-outline btn-sm">Kembali</a>
<?php else: ?>
<div class="d-flex gap-2 flex-wrap mb-3">
<form method="POST" action="<?= $inc_self ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="inc_op" value="hint"><input type="hidden" name="challenge_id" value="<?= (int)$inc_active['id'] ?>"><input type="hidden" name="inc_slug" value="<?= htmlspecialchars($inc_active['slug']) ?>"><input type="hidden" name="level" value="1"><button class="btn btn-cyber-outline btn-sm" type="submit">Hint 1</button></form>
<form method="POST" action="<?= $inc_self ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="inc_op" value="hint"><input type="hidden" name="challenge_id" value="<?= (int)$inc_active['id'] ?>"><input type="hidden" name="inc_slug" value="<?= htmlspecialchars($inc_active['slug']) ?>"><input type="hidden" name="level" value="2"><button class="btn btn-cyber-outline btn-sm" type="submit">Hint 2 (butuh 1x coba)</button></form>
</div>
<?php if (!empty($inc_hint[1])): ?><p class="small mb-1"><strong>Hint 1:</strong> <?= htmlspecialchars($inc_active['hint_lvl1'] ?? '') ?></p><?php endif; ?>
<?php if (!empty($inc_hint[2])): ?><p class="small mb-2"><strong>Hint 2:</strong> <?= htmlspecialchars($inc_active['hint_lvl2'] ?? '') ?></p><?php endif; ?>
<form method="POST" action="<?= $inc_self ?>">
<?= csrf_field() ?><input type="hidden" name="inc_op" value="submit"><input type="hidden" name="challenge_id" value="<?= (int)$inc_active['id'] ?>">
<fieldset class="mb-3"><legend class="h6 fw-bold"><?= htmlspecialchars($inc_active['diagnosis_q']) ?></legend>
<?php foreach ($inc_dopts as $i => $o): ?><label class="wiz-opt d-block mb-1"><input type="radio" name="diagnosis" value="<?= $i ?>" required> <span><?= htmlspecialchars($o) ?></span></label><?php endforeach; ?></fieldset>
<fieldset class="mb-3"><legend class="h6 fw-bold"><?= htmlspecialchars($inc_active['fix_q']) ?></legend>
<?php foreach ($inc_fopts as $i => $o): ?><label class="wiz-opt d-block mb-1"><input type="radio" name="fix" value="<?= $i ?>" required> <span><?= htmlspecialchars($o) ?></span></label><?php endforeach; ?></fieldset>
<button class="btn btn-cyber" type="submit">Submit diagnosis & fix</button> <a href="<?= lab_url('praktik', 'incident') ?>" class="page-actions-link">Batal</a>
</form>
<?php endif; ?>
</section>
<?php else: ?>
<div class="row g-3">
<?php foreach ($inc_list as $c): $done = $c['best'] !== null; ?>
<div class="col-md-6"><div class="card p-4 h-100">
<div class="page-kicker"><?= htmlspecialchars($c['skill']) ?> · <?= htmlspecialchars($c['difficulty']) ?><?= !empty($c['is_pro']) ? ' · PRO' : ' · GRATIS' ?></div>
<h2 class="h6 fw-bold"><?= htmlspecialchars($c['title']) ?></h2>
<?php if (!empty($c['objective'])): ?><p class="small mb-1"><?= htmlspecialchars($c['objective']) ?></p><?php endif; ?>
<p class="small text-muted mb-2"><?= htmlspecialchars(mb_strimwidth($c['story'], 0, 110, '...')) ?></p>
<p class="small mb-2"><?= $done ? 'Terbaik: <strong>' . (int)$c['best'] . '</strong> · ' . (int)$c['tries'] . 'x coba' : 'Belum dicoba' ?> · ~<?= (int)($c['est_minutes'] ?? 10) ?> mnt · +<?= (int)$c['xp_reward'] ?> XP</p>
<a href="<?= lab_url('praktik', 'incident', ['inc_slug' => $c['slug']]) ?>" class="btn <?= $done ? 'btn-cyber-outline' : 'btn-cyber' ?> btn-sm w-100"><?= $done ? 'Ulangi / perbaiki skor' : (!empty($c['is_pro']) && !$inc_pro ? 'Preview + Pro' : 'Mulai incident') ?></a>
</div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
