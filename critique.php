<?php
require_once 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('quests.php');
verify_csrf();
if (rate_limit_hit('critique_post', 20, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('quests.php'); }
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$qid = (int)($_POST['quest_id'] ?? 0);
$owner = (int)($_POST['owner_id'] ?? $uid);
if ($owner <= 0) $owner = $uid;
$note = \App\Domain\Dkv\Critique::clean($_POST['note'] ?? '');
$ok = false;
if ($qid > 0 && \App\Domain\Dkv\Critique::valid($note) && \App\Domain\Dkv\Rubric::canRate($conn, $uid, $owner)) {
    \App\Domain\Dkv\Critique::ensureTables($conn);
    try {
        $s = $conn->prepare("INSERT INTO rubric_comments (quest_id, owner_id, author_id, note) VALUES (?, ?, ?, ?)");
        if ($s) { $s->bind_param("iiis", $qid, $owner, $uid, $note); $ok = $s->execute(); $s->close(); }
    } catch (Throwable $e) {}
}
$back = ($owner !== $uid) ? 'kelas.php' : 'quests.php';
$conn->close();
set_flash($ok ? 'success' : 'warning', $ok ? 'Critique terkirim.' : 'Gagal. Minimal 3 karakter & berhak menilai.');
redirect($back);
