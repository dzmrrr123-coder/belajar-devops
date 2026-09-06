<?php
require_once 'config.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
        exit();
    }

    $conn = db_connect();
    $user_id = (int)$_SESSION['user_id'];
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $duration = isset($_POST['duration']) ? max(1, min(120, (int)$_POST['duration'])) : 25;
    $mode = in_array($_POST['mode'] ?? 'focus', ['focus', 'shortBreak', 'longBreak'], true) ? $_POST['mode'] : 'focus';
    $note = mb_substr(clean($_POST['focus_note'] ?? ''), 0, 255);
    $xp_reward = ($duration >= 25 && $mode === 'focus') ? 10 : 0;

    $chk = $conn->prepare("SELECT completed_at FROM pomodoro_sessions WHERE user_id = ? ORDER BY completed_at DESC LIMIT 1");
    $chk->bind_param("i", $user_id);
    $chk->execute();
    $last = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($last && (time() - strtotime($last['completed_at'])) < 60) {
        $conn->close();
        echo json_encode(['status' => 'error', 'message' => 'Terlalu cepat. Tunggu sebentar sebelum mencatat sesi lagi.']);
        exit();
    }

    $conn->begin_transaction();
    try {
    $stmt = $conn->prepare("INSERT INTO pomodoro_sessions (user_id, duration_minutes, mode, focus_note, completed_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $user_id, $duration, $mode, $note);
    $stmt->execute();
    $pomodoro_id = (int)$stmt->insert_id;
    $stmt->close();

    $xp_reward = apply_xp_multiplier($xp_reward, mission_multiplier($conn, $user_id));
    // Reward XP
    award_xp($conn, $user_id, $xp_reward, 'pomodoro', 'pomodoro', $pomodoro_id);

    // Update streak
    $new_streak = update_user_streak($conn, $user_id);

    // Fetch updated user info
    $stmt = $conn->prepare("SELECT xp, streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Count today's sessions
    $stmt = $conn->prepare("SELECT COUNT(*) as today_sessions, SUM(duration_minutes) as today_minutes FROM pomodoro_sessions WHERE user_id = ? AND DATE(completed_at) = CURDATE()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $new_badges = check_and_unlock_badges($conn, $user_id);
    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("pomodoro error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Gagal mencatat sesi. Coba lagi.']);
        exit();
    }
    $conn->close();

    $new_level = calculate_level($user['xp']);

    echo json_encode([
        'status' => 'success',
        'message' => $xp_reward > 0 ? "Sesi fokus selesai! +{$xp_reward} XP." : "Sesi selesai. Sesi fokus 25 menit memberi +10 XP.",
        'xp' => (int)$user['xp'],
        'level' => $new_level,
        'level_title' => get_user_rank($new_level),
        'level_progress' => level_progress_percent($user['xp']),
        'streak' => (int)$user['streak'],
        'today_sessions' => (int)($stats['today_sessions'] ?? 0),
        'today_minutes' => (int)($stats['today_minutes'] ?? 0),
        'new_badges' => $new_badges ?? []
    ]);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
