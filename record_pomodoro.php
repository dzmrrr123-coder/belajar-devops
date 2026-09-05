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
    $duration = isset($_POST['duration']) ? max(1, min(120, (int)$_POST['duration'])) : 25;
    $xp_reward = 10; // 10 XP per completed focus session

    // Record session
    $stmt = $conn->prepare("INSERT INTO pomodoro_sessions (user_id, duration_minutes, completed_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $user_id, $duration);
    $stmt->execute();
    $stmt->close();

    // Reward XP
    $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
    $stmt->bind_param("ii", $xp_reward, $user_id);
    $stmt->execute();
    $stmt->close();

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

    $conn->close();

    $new_level = calculate_level($user['xp']);

    echo json_encode([
        'status' => 'success',
        'message' => "🍅 Sesi Fokus Selesai! Kamu mendapatkan +{$xp_reward} XP!",
        'xp' => (int)$user['xp'],
        'level' => $new_level,
        'level_title' => get_user_rank($new_level),
        'level_progress' => level_progress_percent($user['xp']),
        'streak' => (int)$user['streak'],
        'today_sessions' => (int)($stats['today_sessions'] ?? 0),
        'today_minutes' => (int)($stats['today_minutes'] ?? 0)
    ]);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
