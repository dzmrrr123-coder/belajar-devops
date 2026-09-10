<?php
require_once 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('quests.php');
verify_csrf();
if (rate_limit_hit('karya_up', 20, 3600)) { set_flash('warning', 'Terlalu sering upload. Coba lagi nanti.'); redirect('quests.php'); }
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
if (user_track($conn, $uid) !== 'dkv' && !is_admin($conn, $uid) && !\App\Domain\Auth\Roles::isGuru($conn, $uid)) {
    $conn->close();
    set_flash('warning', 'Fitur upload karya portofolio khusus untuk jurusan DKV.');
    redirect('quests.php');
}
$qid = (int)($_POST['quest_id'] ?? 0);
$r = ['ok' => false, 'msg' => 'Pilih file gambar dulu (JPG/PNG/WebP/GIF, maks 3 MB).'];
if ($qid > 0 && !empty($_FILES['karya'])) $r = \App\Domain\Dkv\Karya::store($conn, $uid, $qid, $_FILES['karya']);
\App\Domain\Track\Hub::forget($conn, $uid);
$conn->close();
set_flash($r['ok'] ? 'success' : 'warning', $r['ok'] ? 'Karya terupload. Lihat di passport.' : (string)$r['msg']);
redirect('quests.php');
