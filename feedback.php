<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
try { @$conn->query("CREATE TABLE IF NOT EXISTS `product_feedback` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NULL, `kind` VARCHAR(16) NOT NULL DEFAULT 'saran', `message` VARCHAR(1000) NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit_hit('feedback', 5, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('feedback.php'); }
    $kind = in_array($_POST['kind'] ?? '', ['saran', 'bug', 'kepuasan'], true) ? $_POST['kind'] : 'saran';
    $msg = mb_substr(trim(clean($_POST['message'] ?? '')), 0, 1000);
    if (mb_strlen($msg) < 3) { set_flash('warning', 'Tulis feedback minimal 3 karakter.'); redirect('feedback.php'); }
    $ins = $conn->prepare("INSERT INTO product_feedback (user_id, kind, message) VALUES (?, ?, ?)");
    if ($ins) { $ins->bind_param("iss", $uid, $kind, $msg); $ins->execute(); $ins->close(); }
    set_flash('success', 'Feedback tersimpan. Terima kasih!');
    redirect('feedback.php');
}
$mine = [];
try {
    $s = $conn->prepare("SELECT kind, message, created_at FROM product_feedback WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    if ($s) { $s->bind_param("i", $uid); $s->execute(); $mine = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close(); }
} catch (Throwable $e) {}
$conn->close();
$page_title = 'Feedback Produk';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Bantu kami lebih simpel</div>
<h1 class="page-title">Feedback produk</h1>
<p class="page-desc">Saran, bug, atau kepuasan. Satu form simpel — riwayatmu di bawah. Kamu juga bisa buka dari <a href="profile.php#feedback">Profil</a>.</p></div>
<section class="card p-4 mb-3"><form method="POST" class="row g-2"><?= csrf_field() ?>
<div class="col-md-3"><select name="kind" class="form-select"><option value="saran">Saran</option><option value="bug">Bug</option><option value="kepuasan">Kepuasan</option></select></div>
<div class="col-md-7"><input name="message" class="form-control" maxlength="1000" placeholder="Contoh: hint lvl2 membantu, tambah soal K8s…" required></div>
<div class="col-md-2"><button class="btn btn-cyber btn-sm w-100" type="submit">Kirim</button></div>
</form></section>
<section class="card p-4"><h2 class="h6 fw-bold mb-2">Riwayatmu (<?= count($mine) ?>)</h2>
<?php foreach ($mine as $m): ?><div class="list-row"><div class="list-main"><p class="list-title"><?= htmlspecialchars(mb_strimwidth($m['message'], 0, 90, '...')) ?></p><p class="list-meta"><?= htmlspecialchars($m['kind']) ?> · <?= date('d M', strtotime($m['created_at'])) ?></p></div></div><?php endforeach; ?>
<?php if (!$mine): ?><p class="small text-muted mb-0">Belum ada feedback.</p><?php endif; ?></section>
</main>
<?php require_once 'includes/footer.php'; ?>
