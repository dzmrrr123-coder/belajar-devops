<?php
require_once dirname(__DIR__, 3) . '/config.php';
header('Content-Type: application/json');
if (!is_logged_in()) { http_response_code(401); echo json_encode(['status' => 'error']); exit(); }
$conn = db_connect();
$labels = ['quest' => 'quest', 'chest' => 'peti', 'golden_chest' => 'peti emas', 'quiz' => 'kuis', 'focus' => 'fokus', 'pomo' => 'fokus', 'note' => 'catatan', 'error' => 'catatan', 'duel_win' => 'duel', 'season_claim' => 'season', 'mission' => 'misi', 'review' => 'review'];
$items = \App\Cache\Store::remember('activity:global', 300, function () use ($conn, $labels) {
    $out = [];
    try {
        $r = $conn->query("SELECT u.username, e.amount, e.reason, e.created_at FROM xp_events e JOIN users u ON u.id = e.user_id WHERE e.amount > 0 AND u.show_on_board = 1 ORDER BY e.id DESC LIMIT 12");
        if ($r) {
            foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) {
                $lbl = $labels[$row['reason']] ?? 'XP';
                $out[] = ['user' => $row['username'], 'amount' => (int)$row['amount'], 'label' => $lbl, 'at' => $row['created_at']];
            }
            $r->free();
        }
    } catch (Throwable $e) {}
    return $out;
});
echo json_encode(['status' => 'success', 'items' => $items]);
