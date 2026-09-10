<?php
// POST incident untuk lab.php?tab=praktik&alat=incident
$s = $conn->prepare("SELECT id, is_pro, pro_until FROM users WHERE id = ?");
$s->bind_param("i", $uid); $s->execute();
$me = $s->get_result()->fetch_assoc() ?: []; $s->close();
$amPro = \App\Domain\Pro::canAccess($conn, $me, 'incident');
$inc_back = function ($slug = '') {
    redirect('lab.php?tab=praktik&alat=incident' . ($slug !== '' ? '&inc_slug=' . urlencode($slug) : ''));
};
if ($_POST['inc_op'] === 'hint') {
    if (rate_limit_hit('incident_hint', 5, 3600)) { set_flash('warning', 'Hint dibatasi 5/jam. Coba lagi nanti.'); $inc_back((string)($_POST['inc_slug'] ?? '')); }
    $cid = (int)($_POST['challenge_id'] ?? 0);
    $lvl = (int)($_POST['level'] ?? 1);
    $lvl = $lvl === 2 ? 2 : 1;
    $cnt = $conn->prepare("SELECT COUNT(*) c FROM incident_attempts WHERE user_id = ? AND challenge_id = ?");
    $cnt->bind_param("ii", $uid, $cid); $cnt->execute();
    $tries = (int)($cnt->get_result()->fetch_assoc()['c'] ?? 0); $cnt->close();
    if (!\App\Domain\Pro::hintAllowed($lvl - 1, $tries > 0)) { set_flash('warning', 'Coba jawab dulu 1x untuk buka hint lvl 2.'); $inc_back((string)($_POST['inc_slug'] ?? '')); }
    $_SESSION['hint_' . $cid . '_' . $lvl] = true;
    \App\Analytics\Tracker::track($conn, $uid, \App\Analytics\Events::HINT_USED, ['challenge_id' => $cid, 'level' => $lvl]);
    $inc_back((string)($_POST['inc_slug'] ?? ''));
}
if ($_POST['inc_op'] === 'submit') {
    if (rate_limit_hit('incident_submit', 10, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); $inc_back(); }
    $cid = (int)($_POST['challenge_id'] ?? 0);
    $st = $conn->prepare("SELECT * FROM incident_challenges WHERE id = ?");
    $st->bind_param("i", $cid); $st->execute(); $ch = $st->get_result()->fetch_assoc(); $st->close();
    if (!$ch) { set_flash('danger', 'Challenge tidak ditemukan.'); $inc_back(); }
    if (!empty($ch['is_pro']) && !$amPro) { set_flash('warning', 'Challenge ini khusus Pro. Preview gratis, submit butuh Pro.'); redirect('pricing.php'); }
    $diag = (int)($_POST['diagnosis'] ?? -1);
    $fix = (int)($_POST['fix'] ?? -1);
    $start = (int)($_SESSION['incident_start_' . $cid] ?? time());
    $dur = max(5, time() - $start);
    $diagOk = $diag === (int)$ch['diagnosis_correct'];
    $fixOk = $fix === (int)$ch['fix_correct'];
    $sc = \App\Domain\Incident\IncidentBank::score($diagOk, $fixOk, $dur, (int)$ch['time_limit']);
    $xpFull = \App\Domain\Incident\IncidentBank::xpFor($sc['score'], (int)$ch['xp_reward']);
    $already = awarded_for_ref($conn, $uid, 'incident', $cid);
    $gain = max(0, $xpFull - $already);
    $ins = $conn->prepare("INSERT INTO incident_attempts (user_id, challenge_id, score, duration_sec, mistakes, diagnosis_ok, fix_ok) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $di = $diagOk ? 1 : 0; $fi = $fixOk ? 1 : 0;
    $ins->bind_param("iiiiiii", $uid, $cid, $sc['score'], $dur, $sc['mistakes'], $di, $fi);
    $ins->execute(); $ins->close();
    if ($gain > 0) {
        award_xp($conn, $uid, $gain, 'incident', 'incident', $cid);
        \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$ch['skill']), 10, 'incident', 'incident', $cid);
    }
    $nb = check_and_unlock_badges($conn, $uid);
    \App\Analytics\Tracker::track($conn, $uid, \App\Analytics\Events::INCIDENT_COMPLETED, ['challenge_id' => $cid, 'score' => $sc['score']]);
    $cert = \App\Domain\Incident\Certificate::issue($conn, $uid, $cid, $sc['score']);
    unset($_SESSION['incident_start_' . $cid]);
    $_SESSION['lab_inc_result'] = ['ch' => $ch, 'score' => $sc['score'], 'grade' => $sc['grade'], 'mistakes' => $sc['mistakes'], 'dur' => $dur, 'diagOk' => $diagOk, 'fixOk' => $fixOk, 'gain' => $gain, 'badges' => $nb, 'cert' => $cert];
    $inc_back((string)($ch['slug'] ?? ''));
}
