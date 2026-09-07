<?php
require_once 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('quests.php');
verify_csrf();
if (rate_limit_hit('switch_track', 10, 3600)) { set_flash('warning', 'Terlalu sering ganti track. Coba lagi nanti.'); redirect('quests.php'); }
$track = \App\Domain\Track\Tracks::normalize((string)($_POST['track'] ?? 'devops'));
$back = ($_POST['back'] ?? '') === 'profile.php' ? 'profile.php' : 'quests.php';
$conn = db_connect();
try { @$conn->query("ALTER TABLE `users` ADD COLUMN `track` VARCHAR(16) NOT NULL DEFAULT 'devops'"); } catch (Throwable $e) {}
$uid = (int)$_SESSION['user_id'];
$ok = false;
try {
    $up = $conn->prepare("UPDATE users SET track = ? WHERE id = ?");
    if ($up) { $up->bind_param("si", $track, $uid); $ok = $up->execute(); $up->close(); }
} catch (Throwable $e) {}
$conn->close();
$label = \App\Domain\Track\Tracks::all()[$track]['name'] ?? $track;
set_flash($ok ? 'success' : 'danger', $ok ? "Track aktif: {$label}. Quest custom mengikuti track ini." : 'Gagal ganti track. Coba lagi.');
redirect($back);
