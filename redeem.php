<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
\App\Domain\ProVoucher::ensureTables($conn);
$s = $conn->prepare("SELECT is_pro, pro_until, pro_plan FROM users WHERE id = ?");
$s->bind_param("i", $uid); $s->execute();
$me = $s->get_result()->fetch_assoc() ?: []; $s->close();
$amPro = \App\Domain\Pro::canAccess($conn, $me, 'pro');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit_hit('redeem', 10, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('redeem.php'); }
    $code = trim($_POST['code'] ?? '');
    if ($code === '') { set_flash('warning', 'Isi kode voucher dulu.'); redirect('redeem.php'); }
    $r = \App\Domain\ProVoucher::redeem($conn, $uid, $code);
    set_flash($r['ok'] ? 'success' : 'warning', $r['msg']);
    redirect('redeem.php');
}
$conn->close();
$page_title = 'Tukar Voucher Pro';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Tanpa payment gateway · aktivasi manual</div>
<h1 class="page-title">Tukar voucher Pro</h1>
<p class="page-desc">Punya kode dari admin, sekolah, atau bootcamp? Tukarkan di sini.</p>
<?php if ($amPro): ?><p><span class="quest-done"><i class="fas fa-crown"></i>Pro aktif<?= !empty($me['pro_until']) ? ' · s/d ' . htmlspecialchars($me['pro_until']) : '' ?></span></p><?php endif; ?></div>
<section class="card p-4"><form method="POST" class="d-flex gap-2 m-0"><?= csrf_field() ?>
<input name="code" class="form-control" placeholder="VQ-XXXXXXXX" maxlength="16" required aria-label="Kode voucher" style="text-transform:uppercase">
<button class="btn btn-cyber btn-sm flex-shrink-0" type="submit">Tukar</button>
</form>
<p class="small text-muted mt-2 mb-0">Satu kode satu akun. Minta kode baru ke admin jika kuota habis atau kedaluwarsa.</p></section>
</main>
<?php require_once 'includes/footer.php'; ?>
