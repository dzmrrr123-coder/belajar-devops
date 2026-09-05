<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
$key = $_POST['mission_key'] ?? '';
$defs = daily_mission_defs();
verify_csrf();
if (!isset($defs[$key])) {
    $msg = 'Misi tidak valid.';
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash('danger', $msg); redirect('index.php');
}
$status = get_daily_mission_status($conn, $user_id);
if (empty($status[$key]['done'])) {
    $msg = 'Selesaikan dulu misinya hari ini.';
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash('warning', $msg); redirect('index.php');
}
if (!empty($status[$key]['claimed'])) {
    $msg = 'Misi sudah diklaim.';
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash('info', $msg); redirect('index.php');
}
$xp = (int)$defs[$key]['xp'];
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO daily_missions (user_id, mission_date, mission_key, claimed_at) VALUES (?, CURDATE(), ?, NOW()) ON DUPLICATE KEY UPDATE claimed_at = IFNULL(claimed_at, NOW())");
    $stmt->bind_param("is", $user_id, $key);
    $stmt->execute();
    $stmt->close();
    award_xp($conn, $user_id, $xp, 'mission');
    update_user_streak($conn, $user_id);
    $new_badges = check_and_unlock_badges($conn, $user_id);
    $conn->commit();
    $msg = "Misi selesai! +{$xp} XP." . (!empty($new_badges) ? ' Badge: ' . implode(', ', $new_badges) . '!' : '');
    set_flash('success', $msg);
} catch (Throwable $e) {
    $conn->rollback();
    error_log("claim_mission: " . $e->getMessage());
    $msg = 'Gagal klaim misi.';
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash('danger', $msg); redirect('index.php');
}
$conn->close();
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'mission_key' => $key, 'xp_reward' => $xp, 'message' => $msg]);
    exit();
}
redirect('index.php');
