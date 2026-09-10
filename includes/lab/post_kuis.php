<?php
// POST kuis untuk lab.php?tab=kuis (dipakai juga sync.js offline + cards.js AJAX)
if ($paction === 'create') {
    $source = in_array($_POST['source'] ?? '', ['error', 'question'], true) ? $_POST['source'] : 'error';
    $source_id = (int)($_POST['source_id'] ?? 0);
    $question = mb_substr(trim(clean($_POST['question'] ?? '')), 0, 255);
    $answer = mb_substr(trim(clean($_POST['answer'] ?? '')), 0, 2000);
    $qtopic = in_array($_POST['topic'] ?? '', quiz_topics(user_track($conn, $uid)), true) ? $_POST['topic'] : 'General';
    if ($question === '' || $answer === '' || $source_id <= 0) {
        set_flash('warning', 'Pertanyaan, jawaban, dan sumber wajib diisi.');
    } else {
        $ins = $conn->prepare("INSERT INTO quiz_cards (user_id, source, source_id, question, answer, topic) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer), topic = VALUES(topic)");
        $ins->bind_param("isssss", $uid, $source, $source_id, $question, $answer, $qtopic);
        if ($ins->execute()) {
            $cid = $source_id > 0 ? (int)$conn->insert_id : 0;
            if ($cid <= 0) {
                $g = $conn->prepare("SELECT id FROM quiz_cards WHERE user_id = ? AND source = ? AND source_id = ?");
                $g->bind_param("isi", $uid, $source, $source_id);
                $g->execute();
                $cid = (int)($g->get_result()->fetch_assoc()['id'] ?? 0);
                $g->close();
            }
            if ($cid > 0) schedule_review($conn, $uid, 'quiz', $cid, $question, $answer);
            set_flash('success', 'Kartu kuis disimpan. Selamat berlatih!');
        } else {
            set_flash('danger', 'Gagal menyimpan kartu kuis.');
        }
        $ins->close();
    }
    redirect('errors.php');
}

if ($paction === 'answer') {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    $card_id = (int)($_POST['card_id'] ?? 0);
    $result = ($_POST['result'] ?? '') === 'know' ? 'know' : 'forgot';
    $mode = 'blitz';
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? '')))));
    $i = max(0, (int)($_POST['i'] ?? 0));
    $run = $_SESSION['quiz_run'] ?? ['tahu' => 0, 'lupa' => 0, 'xp' => 0];

    $stmt = $conn->prepare("SELECT id, user_id, source, source_id, question, answer, topic, created_at FROM quiz_cards WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $card_id, $uid);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($card) {
        if ($result === 'know') {
            $chk = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND ref_id = ? AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
            $chk->bind_param("ii", $uid, $card_id);
            $chk->execute();
            $already = (int)($chk->get_result()->fetch_assoc()['n'] ?? 0);
            $chk->close();
            if ($already <= 0) {
                $rev = $conn->prepare("SELECT interval_day, done_count FROM reviews WHERE user_id = ? AND source = 'quiz' AND source_id = ?");
                $rev->bind_param("ii", $uid, $card_id);
                $rev->execute();
                $rrow = $rev->get_result()->fetch_assoc();
                $rev->close();
                $next = review_next_interval((int)($rrow['interval_day'] ?? 1));
                $rtitle = mb_substr(trim((string)($card['question'] ?? 'Kuis')), 0, 255);
                $rdetail = mb_substr((string)($card['answer'] ?? ''), 0, 2000);
                $up = $conn->prepare("INSERT INTO reviews (user_id, source, source_id, title, detail, next_due, interval_day, done_count) VALUES (?, 'quiz', ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 1) ON DUPLICATE KEY UPDATE interval_day = VALUES(interval_day), next_due = VALUES(next_due), done_count = done_count + 1");
                $up->bind_param("iissii", $uid, $card_id, $rtitle, $rdetail, $next, $next);
                $up->execute(); $up->close();
                $cap = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
                $cap->bind_param("i", $uid);
                $cap->execute();
                $today_sum = (int)($cap->get_result()->fetch_assoc()['n'] ?? 0);
                $cap->close();
                $gain = min(capped_xp_gain(2, $today_sum, QUIZ_DAILY_XP_CAP), lab_shared_left($conn, $uid));
                if ($gain > 0) {
                    award_xp($conn, $uid, $gain, 'quiz', 'quiz', $card_id);
                    $run['xp'] = ($run['xp'] ?? 0) + $gain;
                }
                \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)($card['topic'] ?? '')), 2, 'quiz', 'quiz', $card_id);
                check_and_unlock_badges($conn, $uid);
            }
            $run['tahu'] = ($run['tahu'] ?? 0) + 1;
        } else {
            $up = $conn->prepare("UPDATE reviews SET interval_day = 1, next_due = DATE_ADD(CURDATE(), INTERVAL 1 DAY), lapses = lapses + 1 WHERE user_id = ? AND source = 'quiz' AND source_id = ?");
            $up->bind_param("ii", $uid, $card_id);
            $up->execute(); $up->close();
            $run['lupa'] = ($run['lupa'] ?? 0) + 1;
        }
    }
    $_SESSION['quiz_run'] = $run;
    $next_i = $i + 1;
    if ($next_i >= count($ids)) {
        try {
            $bi = $conn->prepare("INSERT INTO blitz_runs (user_id, score, total, xp) VALUES (?, ?, ?, ?)");
            if ($bi) {
                $bs = (int)($run['tahu'] ?? 0); $bt = count($ids); $bx = (int)($run['xp'] ?? 0);
                $bi->bind_param("iiii", $uid, $bs, $bt, $bx); $bi->execute(); $bi->close();
            }
        } catch (Throwable $e) {}
        unset($_SESSION['blitz_deadline']);
    }
    if (!empty($is_ajax)) {
        $qc = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'quiz' AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
        $qc->bind_param("i", $uid); $qc->execute();
        $quota_left = min(max(0, QUIZ_DAILY_XP_CAP - (int)($qc->get_result()->fetch_assoc()['n'] ?? 0)), lab_shared_left($conn, $uid));
        $qc->close();
        $next_card = null;
        if ($next_i < count($ids)) {
            $stmt = $conn->prepare("SELECT id, question, answer FROM quiz_cards WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $ids[$next_i], $uid);
            $stmt->execute();
            $next_card = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $conn->close();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 'result' => $result, 'gain' => $gain ?? 0, 'run' => $run,
            'quota_left' => $quota_left, 'pos' => $next_i + 1, 'total' => count($ids), 'next' => $next_card,
        ]);
        exit();
    }
    if ($next_i >= count($ids)) {
        redirect('lab.php?tab=kuis&mode=blitz&done=1');
    }
    redirect('lab.php?tab=kuis&mode=blitz&ids=' . implode(',', $ids) . '&i=' . $next_i);
}
