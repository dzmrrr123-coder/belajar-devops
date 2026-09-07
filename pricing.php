<?php
require_once 'config.php';
$conn = db_connect();
$uid = (int)($_SESSION['user_id'] ?? 0);
$me = null;
$pro = false;
if ($uid) {
    $s = $conn->prepare("SELECT id, username, is_pro, pro_until, pro_plan FROM users WHERE id = ?");
    if ($s) { $s->bind_param("i", $uid); $s->execute(); $me = $s->get_result()->fetch_assoc(); $s->close(); }
    $pro = $me ? \App\Domain\Pro::canAccess($conn, $me, 'pro') : false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit_hit('pro_waitlist', 5, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('pricing.php'); }
    $plan = $_POST['plan'] ?? 'monthly';
    if (!\App\Domain\Pro::validPlan((string)$plan)) $plan = 'monthly';
    $contact = \App\Domain\Pro::cleanContact($_POST['contact'] ?? ($me['username'] ?? ''));
    if (mb_strlen($contact) < 3) { set_flash('warning', 'Isi kontak / WA dulu.'); redirect('pricing.php'); }
    $note = \App\Domain\Pro::cleanContact($_POST['note'] ?? '');
    $ins = $conn->prepare("INSERT INTO pro_waitlist (contact, plan, note, user_id) VALUES (?, ?, ?, ?)");
    $n = $uid ?: null;
    if ($ins) { $ins->bind_param("sssi", $contact, $plan, $note, $n); $ins->execute(); $ins->close(); }
    set_flash('success', 'Masuk waitlist Pro! Kami hubungi saat slot dibuka.');
    redirect('pricing.php');
}
$plans = \App\Domain\Pro::plans();
$wa = getenv('PRO_WA') ?: '';
$qris = getenv('PRO_QRIS_TEXT') ?: 'QRIS manual via admin — klik WA untuk bayar.';
$conn->close();
$page_title = 'Harga Pro';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Freemium · bayar saat butuh hasil</div>
<h1 class="page-title">Gratis buat mulai, Pro buat bukti</h1>
<p class="page-desc">Jual hasil: simulasi nyata, portofolio, sertifikat verifikasi. Bukan XP. Free: 1 lab + roadmap. Pro: 5 lab + hint + sertifikat.</p>
<?php if ($me && $pro): ?><p><span class="quest-done"><i class="fas fa-crown"></i>Pro aktif<?= !empty($me['pro_until']) ? ' · s/d ' . htmlspecialchars($me['pro_until']) : '' ?></span></p><?php endif; ?></div>
<div class="row g-3">
<div class="col-md-4"><div class="card p-4 h-100"><h2 class="h5">Gratis</h2><div class="hero-num">Rp0</div><ul class="small"><?php foreach (\App\Domain\Pro::features('free') as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul><a href="register.php" class="btn btn-cyber-outline btn-sm w-100">Mulai gratis</a></div></div>
<?php foreach (['monthly', 'yearly'] as $k): $p = $plans[$k]; ?>
<div class="col-md-4"><div class="card p-4 h-100<?= $k === 'yearly' ? ' border-warning' : '' ?>"><h2 class="h5"><?= htmlspecialchars($p['name']) ?><?= $k === 'yearly' ? ' · hemat' : '' ?></h2><div class="hero-num"><?= \App\Domain\Pro::rupiah($p['price']) ?><small>/<?= $p['days'] ?> hari</small></div><ul class="small"><?php foreach (\App\Domain\Pro::features('pro') as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
<?php if ($wa): ?><a class="btn btn-cyber btn-sm w-100" href="https://wa.me/<?= htmlspecialchars($wa) ?>?text=<?= urlencode('Halo, saya mau Pro ' . $p['name']) ?>">Bayar via WA</a><?php else: ?><p class="small text-muted"><?= htmlspecialchars($qris) ?></p><?php endif; ?></div></div>
<?php endforeach; ?>
</div>
<div class="card p-4 mt-3"><h2 class="h5">Tim / Kampus / Bootcamp</h2><p class="small text-muted mb-2"><?= \App\Domain\Pro::rupiah($plans['team']['price']) ?>/tahun · dashboard tim, assignment, laporan.</p><form method="POST" class="row g-2"><?= csrf_field() ?><input type="hidden" name="plan" value="team"><div class="col-md-4"><input name="contact" class="form-control" maxlength="140" placeholder="WA / email tim" required></div><div class="col-md-5"><input name="note" class="form-control" maxlength="140" placeholder="Kebutuhan: 20 siswa, onboarding, dsb"></div><div class="col-md-3"><button class="btn btn-cyber btn-sm w-100" type="submit">Join waitlist</button></div></form></div>
<div class="card p-4 mt-3"><h2 class="h5">Sekolah / Kelas · <?= \App\Domain\Pro::rupiah($plans['school']['price']) ?>/tahun</h2><ul class="small"><?php foreach (\App\Domain\Pro::features('school') as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul><form method="POST" class="row g-2"><?= csrf_field() ?><input type="hidden" name="plan" value="school"><div class="col-md-4"><input name="contact" class="form-control" maxlength="140" placeholder="WA guru / sekolah" required></div><div class="col-md-5"><input name="note" class="form-control" maxlength="140" placeholder="Contoh: SMK RPL 30 siswa"></div><div class="col-md-3"><button class="btn btn-cyber btn-sm w-100" type="submit">Ajukan sekolah</button></div></form></div>
<?php if (!$me || !$pro): ?><div class="card p-4 mt-3"><h2 class="h5">Waitlist Pro</h2><form method="POST" class="row g-2"><?= csrf_field() ?><div class="col-md-4"><select name="plan" class="form-select"><option value="monthly">Pro Bulanan</option><option value="yearly">Pro Tahunan</option><option value="waitlist">Ragu — kabari dulu</option></select></div><div class="col-md-5"><input name="contact" class="form-control" maxlength="140" placeholder="WA / email" required></div><div class="col-md-3"><button class="btn btn-cyber-outline btn-sm w-100" type="submit">Ikut waitlist</button></div></form></div><?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
