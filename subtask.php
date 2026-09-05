<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('quests.php'); }
verify_csrf();
$action = $_POST['action'] ?? '';
$quest_id = (int)($_POST['quest_id'] ?? 0);
$ref = $_SERVER['HTTP_REFERER'] ?? 'quests.php';
$respond = function($data) use ($is_ajax, $ref) {
    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode($data); exit(); }
    if (!empty($data['message'])) set_flash($data['status'] === 'success' ? 'success' : 'danger', $data['message']);
    redirect($ref);
};
$qchk = $conn->prepare("SELECT id FROM quests WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
$qchk->bind_param("ii", $quest_id, $user_id);
$qchk->execute();
if (!$qchk->get_result()->fetch_assoc()) { $qchk->close(); $conn->close(); $respond(['status' => 'error', 'message' => 'Quest tidak ditemukan.']); }
$qchk->close();
if ($action === 'create') {
    $title = mb_substr(clean($_POST['title'] ?? ''), 0, 255);
    if ($title === '') $respond(['status' => 'error', 'message' => 'Judul subtask wajib diisi.']);
    $stmt = $conn->prepare("INSERT INTO quest_subtasks (user_id, quest_id, title) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $quest_id, $title);
    $ok = $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close(); $conn->close();
    $respond(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Subtask ditambah.' : 'Gagal menambah.', 'id' => $new_id, 'quest_id' => $quest_id, 'title' => $title]);
}
$sub_id = (int)($_POST['subtask_id'] ?? 0);
if ($sub_id <= 0) { $conn->close(); $respond(['status' => 'error', 'message' => 'Subtask tidak valid.']); }
if ($action === 'toggle') {
    $stmt = $conn->prepare("UPDATE quest_subtasks SET done_at = IF(done_at IS NULL, NOW(), NULL) WHERE id = ? AND user_id = ? AND quest_id = ?");
    $stmt->bind_param("iii", $sub_id, $user_id, $quest_id);
    $stmt->execute(); $stmt->close();
    $stmt = $conn->prepare("SELECT done_at FROM quest_subtasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sub_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $conn->close();
    $respond(['status' => 'success', 'id' => $sub_id, 'quest_id' => $quest_id, 'done' => !empty($row['done_at']), 'message' => 'Subtask diperbarui.']);
}
if ($action === 'set_done') {
    $done = !empty($_POST['done']) ? 1 : 0;
    if ($done) $stmt = $conn->prepare("UPDATE quest_subtasks SET done_at = IFNULL(done_at, NOW()) WHERE id = ? AND user_id = ? AND quest_id = ?");
    else $stmt = $conn->prepare("UPDATE quest_subtasks SET done_at = NULL WHERE id = ? AND user_id = ? AND quest_id = ?");
    $stmt->bind_param("iii", $sub_id, $user_id, $quest_id);
    $stmt->execute(); $stmt->close(); $conn->close();
    $respond(['status' => 'success', 'id' => $sub_id, 'quest_id' => $quest_id, 'done' => (bool)$done, 'message' => 'Subtask diperbarui.']);
}
if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM quest_subtasks WHERE id = ? AND user_id = ? AND quest_id = ?");
    $stmt->bind_param("iii", $sub_id, $user_id, $quest_id);
    $stmt->execute(); $stmt->close(); $conn->close();
    $respond(['status' => 'success', 'id' => $sub_id, 'quest_id' => $quest_id, 'message' => 'Subtask dihapus.']);
}
$conn->close();
$respond(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
