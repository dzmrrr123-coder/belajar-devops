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
    if (rate_limit_hit('quest_toggle', 30, 60)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Terlalu cepat. Tunggu sebentar.']);
            exit();
        }
        set_flash('warning', 'Terlalu cepat. Tunggu sebentar.');
        redirect('quests.php');
    }
    if ($quest_id <= 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Quest tidak valid.']);
            exit();
        }
        set_flash('danger', 'Quest tidak valid.');
        redirect('quests.php');
    }

    if ($is_ajax && session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $conn->begin_transaction();
    try {
    // Fetch quest info (global atau milik sendiri saja)
    $stmt = $conn->prepare("SELECT id, user_id, week, title, xp_reward, depends_on FROM quests WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
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

    $done_ids = [];
    $dq = $conn->prepare("SELECT quest_id FROM user_quests WHERE user_id = ?");
    $dq->bind_param("i", $user_id);
    $dq->execute();
    foreach ($dq->get_result()->fetch_all(MYSQLI_ASSOC) as $dr) $done_ids[(int)$dr['quest_id']] = true;
    $dq->close();

    $prev_global = null;
    if (empty($quest['user_id'])) {
        $pq = $conn->prepare("SELECT id FROM quests WHERE user_id IS NULL AND (week < ? OR (week = ? AND id < ?)) ORDER BY week DESC, id DESC LIMIT 1");
        $qw = (int)$quest['week'];
        $pq->bind_param("iii", $qw, $qw, $quest_id);
        $pq->execute();
        $pr = $pq->get_result()->fetch_assoc();
        $pq->close();
        $prev_global = $pr ? (int)$pr['id'] : null;
    }

    if (!$existing) {
        $blocker = quest_blocker(['id' => $quest_id, 'user_id' => $quest['user_id'], 'depends_on' => $quest['depends_on']], $done_ids, $prev_global);
        if ($blocker !== null) {
            $bt = $conn->prepare("SELECT title FROM quests WHERE id = ?");
            $bt->bind_param("i", $blocker);
            $bt->execute();
            $brow = $bt->get_result()->fetch_assoc();
            $bt->close();
            $btitle = (string)($brow['title'] ?? 'quest sebelumnya');
            $conn->rollback();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Terkunci. Selesaikan "' . $btitle . '" dulu.']);
                exit();
            }
            set_flash('warning', 'Quest terkunci. Selesaikan "' . $btitle . '" dulu.');
            redirect('quests.php');
        }
    }

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
        award_xp($conn, $user_id, $xp_reward, 'quest', 'quest', $quest_id);

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
        if (!$is_ajax) set_flash('success', $msg);
    } else {
        // Undo Quest completion
        $stmt = $conn->prepare("DELETE FROM user_quests WHERE id = ?");
        $stmt->bind_param("i", $existing['id']);
        $stmt->execute();
        $stmt->close();

        $awarded = awarded_for_ref($conn, $user_id, 'quest', $quest_id);
        $deduct = $awarded > 0 ? $awarded : apply_xp_multiplier($xp_reward, mission_multiplier($conn, $user_id));
        award_xp($conn, $user_id, -$deduct, 'quest_undo', 'quest', $quest_id);
        $xp_reward = $deduct;

        delete_review($conn, $user_id, 'quest', $quest_id);
        $new_xp = sync_user_xp($conn, $user_id);
        $new_level = calculate_level($new_xp);
        $new_streak = (int)$u_data['streak'];
        $action = 'uncompleted';

        $msg = "Quest ditandai belum selesai. -{$deduct} XP disesuaikan.";
        if (!$is_ajax) set_flash('info', $msg);
    }

    // Refresh user state for response
    $stmt = $conn->prepare("SELECT xp, streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $updated_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $quests_done = 0;
    $quests_total = 0;
    $cnt = $conn->prepare("SELECT (SELECT COUNT(*) FROM user_quests WHERE user_id = ?) AS done, (SELECT COUNT(*) FROM quests WHERE user_id IS NULL OR user_id = ?) AS total");
    if ($cnt) {
        $cnt->bind_param("ii", $user_id, $user_id);
        $cnt->execute();
        $cc = $cnt->get_result()->fetch_assoc() ?: [];
        $quests_done = (int)($cc['done'] ?? 0);
        $quests_total = (int)($cc['total'] ?? 0);
        $cnt->close();
    }

    if ($action === 'completed') $new_badges = check_and_unlock_badges($conn, $user_id);
    $newly_unlocked = [];
    if ($action === 'completed') {
        $gq = $conn->prepare("SELECT id, user_id, week, title, depends_on FROM quests WHERE user_id IS NULL ORDER BY week ASC, id ASC");
        $gq->execute();
        $globals = $gq->get_result()->fetch_all(MYSQLI_ASSOC);
        $gq->close();
        $pmap = quest_prev_map($globals);
        $done_ids[$quest_id] = true;
        foreach ($globals as $g) {
            $gid = (int)$g['id'];
            if ($gid === $quest_id || isset($done_ids[$gid])) continue;
            $eff = isset($g['depends_on']) && (int)$g['depends_on'] > 0 ? (int)$g['depends_on'] : ($pmap[$gid] ?? null);
            if ($eff === $quest_id) $newly_unlocked[] = ['id' => $gid, 'title' => (string)$g['title']];
        }
        if (!empty($newly_unlocked) && !$is_ajax) {
            $names = array_map(fn($u) => '"' . $u['title'] . '"', array_slice($newly_unlocked, 0, 2));
            $msg .= ' Terbuka: ' . implode(', ', $names) . '.';
        }
    }
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
            'xp_delta' => $action === 'completed' ? (int)$xp_reward : -(int)$xp_reward,
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
            'newly_unlocked' => $newly_unlocked ?? [],
            'message' => $msg . (!empty($new_badges) ? ' Badge baru: ' . implode(', ', $new_badges) . '!' : '')
        ]);
        exit();
    }
} else {
    $conn->close();
}

$redirect_url = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'quests.php';
redirect($redirect_url);
