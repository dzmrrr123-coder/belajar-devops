<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

$fail = function ($msg) use ($is_ajax) {
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => $msg]); exit(); }
    set_flash('warning', $msg);
    redirect($_SERVER['HTTP_REFERER'] ?? 'leaderboard.php');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail('Metode tidak valid.');
verify_csrf();
if (rate_limit_hit('react', 30, 60)) $fail('Terlalu cepat. Tunggu sebentar.');

$type = $_POST['target_type'] ?? 'profile';
$target = (int)($_POST['target_id'] ?? 0);
$emoji = (string)($_POST['emoji'] ?? '');
if ($type !== 'profile' || $target <= 0 || !\App\Domain\Social\Reactions::valid($emoji)) $fail('Reaksi tidak valid.');
if ($target === $user_id) $fail('Tidak bisa memberi reaksi ke diri sendiri.');

$added = \App\Domain\Social\Reactions::toggle($conn, $user_id, $type, $target, $emoji);
$counts = \App\Domain\Social\Reactions::counts($conn, $type, [$target]);
$mine = \App\Domain\Social\Reactions::mine($conn, $user_id, $type, [$target]);
$conn->close();

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'added' => $added, 'counts' => $counts[$target] ?? [], 'mine' => $mine[$target] ?? []]);
    exit();
}
set_flash('success', $added ? 'Reaksi terkirim!' : 'Reaksi dibatalkan.');
redirect($_SERVER['HTTP_REFERER'] ?? 'leaderboard.php');
