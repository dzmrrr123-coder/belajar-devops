<?php
require_once 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('quests.php');
verify_csrf();
if (rate_limit_hit('rubric_save', 20, 3600)) { set_flash('warning', 'Terlalu sering menilai. Coba lagi nanti.'); redirect('quests.php'); }
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$qid = (int)($_POST['quest_id'] ?? 0);
$ok = false;
if ($qid > 0) {
    try {
        $chk = $conn->prepare("SELECT q.id FROM quests q JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE q.id = ? AND (q.user_id IS NULL OR q.user_id = ?)");
        if ($chk) {
            $chk->bind_param("iii", $uid, $qid, $uid); $chk->execute();
            $found = (bool)$chk->get_result()->fetch_assoc(); $chk->close();
            if ($found) {
                $scores = [];
                foreach ((array)($_POST['score'] ?? []) as $slug => $v) $scores[(string)$slug] = (int)$v;
                $note = mb_substr(trim(clean($_POST['note'] ?? '')), 0, 300);
                $ok = \App\Domain\Dkv\Rubric::save($conn, $uid, $qid, $uid, $scores, $note);
            }
        }
    } catch (Throwable $e) {}
}
$conn->close();
set_flash($ok ? 'success' : 'warning', $ok ? 'Nilai rubrik tersimpan. Lihat di passport.' : 'Gagal menyimpan. Selesaikan quest dulu, lalu nilai.');
redirect('quests.php');
