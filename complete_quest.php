<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quest_id'])) {
    // Verify CSRF for security
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token. Please refresh.']);
            exit();
        }
        set_flash('danger', 'Token keamanan tidak valid.');
        redirect('quests.php');
    }

    $quest_id = (int)$_POST['quest_id'];
    if ($quest_id <= 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Quest tidak valid.']);
            exit();
        }
        set_flash('danger', 'Quest tidak valid.');
        redirect('quests.php');
    }

    $conn->begin_transaction();
    try {
    // Fetch quest info (global atau milik sendiri saja)
    $stmt = $conn->prepare("SELECT id, title, xp_reward FROM quests WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
    $stmt->bind_param("ii", $quest_id, $user_id);
    $stmt->execute();
    $q_res = $stmt->get_result();
    $quest = $q_res->fetch_assoc();
    $stmt->close();

    if (!$quest) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Quest tidak ditemukan!']);
            exit();
        }
        set_flash('danger', 'Quest tidak ditemukan.');
        redirect('quests.php');
    }

    $xp_reward = (int)$quest['xp_reward'];

    // Check if already completed
    $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id = ? AND quest_id = ?");
    $stmt->bind_param("ii", $user_id, $quest_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $action = '';
    $leveled_up = false;
    $new_badges = [];

    // Get current user XP
    $u_stmt = $conn->prepare("SELECT xp, streak FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_data = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    $old_level = calculate_level($u_data['xp']);

    if (!$existing) {
        // Complete Quest
        $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id, completed_at) VALUES (?, ?, CURDATE())");
        $stmt->bind_param("ii", $user_id, $quest_id);
        $stmt->execute();
        $stmt->close();

        $mult = mission_multiplier($conn, $user_id);
        $xp_reward = apply_xp_multiplier($xp_reward, $mult);
        // Add XP
        award_xp($conn, $user_id, $xp_reward, 'quest');

        // Update streak
        $new_streak = update_user_streak($conn, $user_id);
        $action = 'completed';

        // Check level up
        $new_xp = $u_data['xp'] + $xp_reward;
        $new_level = calculate_level($new_xp);
        if ($new_level > $old_level) {
            $leveled_up = true;
        }

        schedule_review($conn, $user_id, 'quest', $quest_id, $quest['title'] ?? 'Quest', '');
        $msg = "Quest diselesaikan! +{$xp_reward} XP.";
        if ($leveled_up) {
            $msg .= " Naik ke Level {$new_level} (" . get_user_rank($new_level) . ")!";
        }
        set_flash('success', $msg);
    } else {
        // Undo Quest completion
        $stmt = $conn->prepare("DELETE FROM user_quests WHERE id = ?");
        $stmt->bind_param("i", $existing['id']);
        $stmt->execute();
        $stmt->close();

        // Deduct XP (never below 0)
        $stmt = $conn->prepare("UPDATE users SET xp = GREATEST(0, xp - ?) WHERE id = ?");
        $stmt->bind_param("ii", $xp_reward, $user_id);
        $stmt->execute();
        $stmt->close();

        delete_review($conn, $user_id, 'quest', $quest_id);
        $new_xp = max(0, $u_data['xp'] - $xp_reward);
        $new_level = calculate_level($new_xp);
        $new_streak = (int)$u_data['streak'];
        $action = 'uncompleted';

        $msg = "Quest ditandai belum selesai. -{$xp_reward} XP disesuaikan.";
        set_flash('info', $msg);
    }

    // Refresh user state for response
    $stmt = $conn->prepare("SELECT xp, streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $updated_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $quests_done = 0;
    $quests_total = 0;
    $cnt = $conn->prepare("SELECT COUNT(*) AS done FROM user_quests WHERE user_id = ?");
    if ($cnt) {
        $cnt->bind_param("i", $user_id);
        $cnt->execute();
        $quests_done = (int)($cnt->get_result()->fetch_assoc()['done'] ?? 0);
        $cnt->close();
    }
    $tot = $conn->prepare("SELECT COUNT(*) AS total FROM quests WHERE user_id IS NULL OR user_id = ?");
    if ($tot) {
        $tot->bind_param("i", $user_id);
        $tot->execute();
        $quests_total = (int)($tot->get_result()->fetch_assoc()['total'] ?? 0);
        $tot->close();
    }

    if ($action === 'completed') $new_badges = check_and_unlock_badges($conn, $user_id);
    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("complete_quest error: " . $e->getMessage());
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Gagal memproses quest. Coba lagi.']);
            exit();
        }
        set_flash('danger', 'Gagal memproses quest. Coba lagi.');
        redirect('quests.php');
    }
    $conn->close();

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'action' => $action,
            'quest_id' => $quest_id,
            'xp_reward' => $xp_reward,
            'xp' => (int)$updated_user['xp'],
            'level' => calculate_level($updated_user['xp']),
            'level_title' => get_user_rank(calculate_level($updated_user['xp'])),
            'level_progress' => level_progress_percent($updated_user['xp']),
            'next_level_xp' => xp_to_next_level($updated_user['xp']),
            'streak' => (int)$updated_user['streak'],
            'leveled_up' => $leveled_up,
            'quests_done' => $quests_done,
            'quests_total' => $quests_total,
            'new_badges' => $new_badges,
            'message' => $msg . (!empty($new_badges) ? ' Badge baru: ' . implode(', ', $new_badges) . '!' : '')
        ]);
        exit();
    }
} else {
    $conn->close();
}

$redirect_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'quests.php';
redirect($redirect_url);
