<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
verify_csrf();

$fail = function ($msg, $type = 'info') use ($is_ajax) {
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash($type, $msg); redirect('hub.php');
};

try {
    $chk = $conn->prepare("SELECT xp FROM daily_chests WHERE user_id = ? AND chest_date = CURDATE()");
    $chk->bind_param("i", $user_id);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
} catch (Throwable $e) { $existing = null; }
if ($existing) $fail('Peti hari ini sudah dibuka. Kembali besok!');

$xp = 8;
$freeze = 0;

$conn->begin_transaction();
try {
    $ins = $conn->prepare("INSERT INTO daily_chests (user_id, chest_date, xp, `freeze`) VALUES (?, CURDATE(), ?, ?)");
    $ins->bind_param("iii", $user_id, $xp, $freeze);
    $ins->execute();
    $ins->close();
    award_xp($conn, $user_id, $xp, 'chest');
    $conn->commit();
    $msg = "Peti dibuka! +{$xp} XP.";
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'xp' => $xp, 'freeze' => 0, 'rare' => false, 'tier' => 'common', 'golden' => false, 'message' => $msg]);
        exit();
    }
    set_flash('success', $msg);
} catch (Throwable $e) {
    $conn->rollback();
    error_log("claim_chest: " . $e->getMessage());
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => 'Gagal membuka peti.']); exit(); }
    set_flash('danger', 'Gagal membuka peti.');
}
$conn->close();
redirect('hub.php');
