<?php
require_once dirname(__DIR__, 3) . '/config.php';
header('Content-Type: application/json');
if (!is_logged_in()) { http_response_code(401); echo json_encode(['status' => 'error']); exit(); }
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
try {
    $s = $conn->prepare("SELECT xp, streak FROM users WHERE id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $u = $s->get_result()->fetch_assoc() ?: ['xp' => 0, 'streak' => 0]; $s->close();
    $w = $conn->prepare("SELECT GREATEST(0, COALESCE(SUM(amount),0)) n FROM xp_events WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $w->bind_param("i", $uid); $w->execute();
    $wxp = (int)($w->get_result()->fetch_assoc()['n'] ?? 0); $w->close();
    $m = get_daily_mission_status($conn, $uid);
    $done = count(array_filter($m, fn($x) => !empty($x['done'])));
    $claimed = count(array_filter($m, fn($x) => !empty($x['claimed'])));
    echo json_encode(['status' => 'success', 'xp' => (int)$u['xp'], 'level' => calculate_level((int)$u['xp']), 'streak' => (int)$u['streak'], 'xp_week' => $wxp, 'missions_done' => $done, 'missions_claimed' => $claimed]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
