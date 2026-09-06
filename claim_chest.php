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
    set_flash($type, $msg); redirect('index.php');
};

try {
    $chk = $conn->prepare("SELECT xp, `freeze` FROM daily_chests WHERE user_id = ? AND chest_date = CURDATE()");
    $chk->bind_param("i", $user_id);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
} catch (Throwable $e) { $existing = null; }
if ($existing) $fail('Peti hari ini sudah dibuka. Kembali besok!');

// Undian berbobot: 60% kecil, 30% sedang, 10% besar; freeze 15%
try {
    $r = random_int(1, 100);
} catch (Throwable $e) { $r = 50; }
if ($r <= 60) { try { $xp = random_int(3, 7); } catch (Throwable $e) { $xp = 5; } }
elseif ($r <= 90) { try { $xp = random_int(8, 12); } catch (Throwable $e) { $xp = 10; } }
else { try { $xp = random_int(13, 15); } catch (Throwable $e) { $xp = 14; } }
try { $freeze = random_int(1, 100) <= 15 ? 1 : 0; } catch (Throwable $e) { $freeze = 0; }

$conn->begin_transaction();
try {
    $ins = $conn->prepare("INSERT INTO daily_chests (user_id, chest_date, xp, `freeze`) VALUES (?, CURDATE(), ?, ?)");
    $ins->bind_param("iii", $user_id, $xp, $freeze);
    $ins->execute();
    $ins->close();
    award_xp($conn, $user_id, $xp, 'chest');
    if ($freeze > 0) {
        $up = $conn->prepare("UPDATE users SET freeze_tokens = LEAST(3, freeze_tokens + 1) WHERE id = ?");
        $up->bind_param("i", $user_id);
        $up->execute(); $up->close();
    }
    $conn->commit();
    $rare = ($xp >= 10 || $freeze > 0);
    $msg = "Peti dibuka! +{$xp} XP" . ($freeze > 0 ? ' + 1 freeze' : '') . ".";
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'xp' => $xp, 'freeze' => $freeze, 'rare' => $rare, 'message' => $msg]);
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
redirect('index.php');
