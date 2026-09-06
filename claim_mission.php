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
$claim_all = ($key === 'all');
if (!$claim_all && !isset($defs[$key])) {
    $msg = 'Misi tidak valid.';
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash('danger', $msg); redirect('index.php');
}
$keys = $claim_all ? array_keys($defs) : [$key];
$status = get_daily_mission_status($conn, $user_id);
if ($is_ajax && session_status() === PHP_SESSION_ACTIVE) session_write_close();
$conn->begin_transaction();
$got = 0; $got_xp = 0;
try {
    foreach ($keys as $k) {
        if (empty($status[$k]['done']) || !empty($status[$k]['claimed'])) continue;
        $xp = (int)$defs[$k]['xp'];
        $stmt = $conn->prepare("INSERT INTO daily_missions (user_id, mission_date, mission_key, claimed_at) VALUES (?, CURDATE(), ?, NOW()) ON DUPLICATE KEY UPDATE claimed_at = IFNULL(claimed_at, NOW())");
        $stmt->bind_param("is", $user_id, $k);
        $stmt->execute();
        $stmt->close();
        award_xp($conn, $user_id, $xp, 'mission');
        $status[$k]['claimed'] = true;
        $got++; $got_xp += $xp;
    }
    if ($got > 0) {
        update_user_streak($conn, $user_id);
        $new_badges = check_and_unlock_badges($conn, $user_id);
    } else {
        $new_badges = [];
    }
    $conn->commit();
    $msg = $got > 0
        ? ($claim_all ? "Semua misi diklaim! +{$got_xp} XP." : "Misi selesai! +{$got_xp} XP.") . (!empty($new_badges) ? ' Badge: ' . implode(', ', $new_badges) . '!' : '')
        : 'Tidak ada misi yang bisa diklaim.';
    if (!$is_ajax && session_status() === PHP_SESSION_ACTIVE) set_flash($got > 0 ? 'success' : 'info', $msg);
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
    echo json_encode(['status' => $got > 0 ? 'success' : 'error', 'mission_key' => $key, 'xp_reward' => $got_xp, 'message' => $msg]);
    exit();
}
redirect('index.php');
