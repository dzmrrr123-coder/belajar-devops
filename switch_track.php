<?php
require_once 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('quests.php');
verify_csrf();
if (rate_limit_hit('switch_track', 10, 3600)) { set_flash('warning', 'Terlalu sering ganti track. Coba lagi nanti.'); redirect('quests.php'); }
$conn = db_connect();
try { @$conn->query("ALTER TABLE `users` ADD COLUMN `track` VARCHAR(16) NOT NULL DEFAULT 'devops'"); } catch (Throwable $e) {}
$uid = (int)$_SESSION['user_id'];
$inClass = false;
try {
    $c = $conn->prepare("SELECT COUNT(*) c FROM squad_members WHERE user_id = ?");
    if ($c) { $c->bind_param("i", $uid); $c->execute(); $inClass = ((int)($c->get_result()->fetch_assoc()['c'] ?? 0)) > 0; $c->close(); }
} catch (Throwable $e) {}
$priv = false;
try { $priv = is_admin($conn, $uid) || \App\Domain\Auth\Roles::isGuru($conn, $uid); } catch (Throwable $e) {}
if ($inClass && !$priv) {
    $conn->close();
    set_flash('warning', 'Track terkunci oleh kelas. Hubungi guru pembimbing untuk mengganti jurusan.');
    redirect('quests.php');
}
$track = \App\Domain\Track\Tracks::normalize((string)($_POST['track'] ?? 'devops'));
$back = trim($_POST['back'] ?? 'quests.php');
// Sanitize back redirect to internal safe paths
$allowed_backs = ['quests.php', 'profile.php', 'index.php', 'lab.php', 'brief.php', 'topologi.php', 'terminal.php', 'playground.php', 'incident.php'];
$clean_back = 'quests.php';
foreach ($allowed_backs as $ab) {
    if (str_contains($back, $ab)) { $clean_back = $ab; break; }
}

$ok = false;
try {
    $up = $conn->prepare("UPDATE users SET track = ? WHERE id = ?");
    if ($up) { $up->bind_param("si", $track, $uid); $ok = $up->execute(); $up->close(); }
    \App\Domain\Track\Roadmap::ensureSeed($conn);
} catch (Throwable $e) {}
$conn->close();
$label = \App\Domain\Track\Tracks::all()[$track]['name'] ?? $track;
set_flash($ok ? 'success' : 'danger', $ok ? "Jurusan aktif diubah ke: {$label}. Roadmap, materi, dan lab otomatis menyesuaikan!" : 'Gagal ganti jurusan. Coba lagi.');
redirect($clean_back);
