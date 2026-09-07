<?php
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

function smoke_fail($msg) {
    fwrite(STDERR, "SMOKE FAIL: {$msg}" . PHP_EOL);
    exit(1);
}

$conn = db_connect();
$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) $tables[] = $row[0];
foreach (['users', 'quests', 'user_quests', 'xp_events', 'reviews', 'challenges', 'cheers'] as $t) {
    if (!in_array($t, $tables, true)) smoke_fail("missing table {$t}");
}

$conn->begin_transaction();
try {
    $u = 'smoke_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $hash = password_hash('smoke123', PASSWORD_BCRYPT);
    $s = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $em = $u . '@test.local';
    $s->bind_param("sss", $u, $em, $hash);
    if (!$s->execute()) smoke_fail('insert user');
    $uid = (int)$s->insert_id;
    $s->close();

    $q = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward) VALUES (?, 1, 1, 'Smoke quest', 'd', 10)");
    $q->bind_param("i", $uid);
    if (!$q->execute()) smoke_fail('insert quest');
    $qid = (int)$q->insert_id;
    $q->close();

    award_xp($conn, $uid, 25, 'quest', 'quest', $qid);
    $ledger = xp_ledger_sum($conn, $uid);
    if ($ledger !== 25) smoke_fail("ledger {$ledger} != 25");
    $cur = sync_user_xp($conn, $uid);
    if ($cur !== 25) smoke_fail("sync {$cur} != 25");

    $lvl = calculate_level($cur);
    if ($lvl !== 1) smoke_fail("level {$lvl} != 1");

    $ch = ensure_weekly_challenge($conn);
    if (empty($ch['id'])) smoke_fail('no challenge');

    $gc = $conn->query("SHOW COLUMNS FROM `daily_chests` LIKE 'is_golden'");
    if (!$gc || $gc->num_rows === 0) smoke_fail('no is_golden column');
    if ($gc) $gc->free();

    foreach (['squads', 'squad_members', 'duels', 'reactions'] as $t) {
        $chk = $conn->query("SHOW TABLES LIKE '{$t}'");
        if (!$chk || $chk->num_rows === 0) smoke_fail("missing table {$t}");
        if ($chk) $chk->free();
    }
    if (\App\Domain\Auth\Roles::hasRole($conn, $uid, 'admin')) smoke_fail('new user should not be admin');
    $u2 = $u . '_b';
    $s2 = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $em2 = $u2 . '@test.local';
    $s2->bind_param("sss", $u2, $em2, $hash);
    if (!$s2->execute()) smoke_fail('insert user 2');
    $uid2 = (int)$s2->insert_id;
    $s2->close();
    if (!\App\Domain\Auth\Roles::grant($conn, $uid, 'admin')) smoke_fail('grant admin');
    if (!\App\Domain\Auth\Roles::grant($conn, $uid2, 'admin')) smoke_fail('grant admin 2');
    if (!\App\Domain\Auth\Roles::hasRole($conn, $uid, 'admin')) smoke_fail('hasRole after grant');
    if (!\App\Http\Auth::isAdmin($conn, $uid)) smoke_fail('isAdmin after grant');
    if (!\App\Domain\Auth\Roles::revoke($conn, $uid, 'admin')) smoke_fail('revoke admin');
    if (\App\Domain\Auth\Roles::hasRole($conn, $uid, 'admin')) smoke_fail('hasRole after revoke');
    \App\Domain\Auth\Roles::reset();
    if (\App\Domain\Auth\Roles::revoke($conn, $uid2, 'admin')) {
        $left = \App\Domain\Auth\Roles::countAdmins($conn);
        if ($left === 0) smoke_fail('last admin revoked');
    }
    \App\Domain\Auth\Roles::reset();

    $sq = \App\Domain\Social\Squads::create($conn, $uid, 'Smoke Squad');
    if (empty($sq['ok'])) smoke_fail('squad create: ' . ($sq['msg'] ?? ''));
    if (\App\Domain\Social\Squads::mySquadId($conn, $uid) !== (int)$sq['id']) smoke_fail('squad member');
    $det = \App\Domain\Social\Squads::detail($conn, (int)$sq['id']);
    if (empty($det['members']) || count($det['members']) !== 1) smoke_fail('squad detail');
    if (!\App\Domain\Social\Squads::leave($conn, $uid)) smoke_fail('squad leave');
    if (\App\Domain\Social\Squads::mySquadId($conn, $uid) !== null) smoke_fail('squad leave check');
    $du = \App\Domain\Social\Duels::challenge($conn, $uid, $u2);
    if (empty($du['ok'])) smoke_fail('duel challenge: ' . ($du['msg'] ?? ''));
    $duels = \App\Domain\Social\Duels::myDuels($conn, $uid);
    if (count($duels) !== 1 || ($duels[0]['status'] ?? '') !== 'pending') smoke_fail('duel pending');
    $did = (int)$duels[0]['id'];
    if (!\App\Domain\Social\Duels::accept($conn, $uid2, $did)) smoke_fail('duel accept');
    if (\App\Domain\Social\Duels::finishable(['status' => 'active', 'week_key' => '2000-W01', 'created_at' => '2000-01-01 00:00:00']) !== true) smoke_fail('duel finishable past');
    if (!\App\Domain\Social\Reactions::toggle($conn, $uid, 'profile', $uid2, 'fire')) smoke_fail('react add');
    if (\App\Domain\Social\Reactions::toggle($conn, $uid, 'profile', $uid2, 'fire')) smoke_fail('react toggle off should return false');
    $rc = \App\Domain\Social\Reactions::counts($conn, 'profile', [$uid2]);
    if (($rc[$uid2]['fire'] ?? 0) !== 0) smoke_fail('react count after untoggle');
    \App\Domain\Auth\Roles::reset();

    foreach (['season_premium', 'season_claims', 'blitz_runs', 'user_frames'] as $t) {
        $chk = $conn->query("SHOW TABLES LIKE '{$t}'");
        if (!$chk || $chk->num_rows === 0) smoke_fail("missing table {$t}");
        if ($chk) $chk->free();
    }
    $skey = \App\Domain\Gamification\Season::key();
    award_xp($conn, $uid, 700, 'quest', 'quest', $qid);
    if (\App\Domain\Gamification\Season::seasonXp($conn, $uid, $skey) < 700) smoke_fail('season xp');
    $buy = \App\Domain\Gamification\Season::buy($conn, $uid, $skey);
    if (empty($buy['ok'])) smoke_fail('season buy: ' . ($buy['msg'] ?? ''));
    if (!\App\Domain\Gamification\Season::hasPremium($conn, $uid, $skey)) smoke_fail('season premium flag');
    $cl = \App\Domain\Gamification\Season::claim($conn, $uid, $skey, 1, 'free');
    if (empty($cl['ok'])) smoke_fail('season claim free: ' . ($cl['msg'] ?? ''));
    $cl2 = \App\Domain\Gamification\Season::claim($conn, $uid, $skey, 1, 'premium');
    if (empty($cl2['ok'])) smoke_fail('season claim premium: ' . ($cl2['msg'] ?? ''));
    $cl3 = \App\Domain\Gamification\Season::claim($conn, $uid, $skey, 1, 'free');
    if (!empty($cl3['ok'])) smoke_fail('season double claim should fail');
    $bi = $conn->prepare("INSERT INTO blitz_runs (user_id, score, total, xp) VALUES (?, 7, 10, 14)");
    $bi->bind_param("i", $uid);
    if (!$bi->execute()) smoke_fail('blitz insert');
    $bi->close();
    \App\Analytics\Tracker::track($conn, $uid, \App\Analytics\Events::QUEST_COMPLETED, ['quest_id' => $qid, 'xp' => 25]);
    \App\Analytics\Tracker::track($conn, $uid, 'bogus_event', []);
    $ae = $conn->prepare("SELECT COUNT(*) n FROM analytics_events WHERE user_id = ? AND event = 'quest_completed'");
    $ae->bind_param("i", $uid);
    $ae->execute();
    if ((int)($ae->get_result()->fetch_assoc()['n'] ?? 0) !== 1) smoke_fail('analytics track');
    $ae->close();
    if (in_array('aurora', \App\Domain\Shop::ownedFrames($conn, $uid), true)) smoke_fail('frame should be locked');
    if (!\App\Domain\Shop::grantFrame($conn, $uid, 'aurora')) smoke_fail('frame grant');
    if (!in_array('aurora', \App\Domain\Shop::ownedFrames($conn, $uid), true)) smoke_fail('frame owns');
    $chk = $conn->query("SHOW TABLES LIKE 'evidence_submissions'");
    if (!$chk || $chk->num_rows === 0) smoke_fail('missing evidence_submissions');
    if ($chk) $chk->free();
    if (!\App\Domain\Quest\Evidence::save($conn, $uid, $qid, 'https://example.com/repo', 'selesai')) smoke_fail('evidence save');
    if (\App\Domain\Quest\Evidence::save($conn, $uid, $qid, '', '')) smoke_fail('evidence empty should skip');
    $evm = \App\Domain\Quest\Evidence::forQuests($conn, $uid, [$qid]);
    if (($evm[$qid]['url'] ?? '') !== 'https://example.com/repo') smoke_fail('evidence fetch');
    \App\Domain\Quest\Evidence::clear($conn, $uid, $qid);
    $evm = \App\Domain\Quest\Evidence::forQuests($conn, $uid, [$qid]);
    if (isset($evm[$qid])) smoke_fail('evidence clear');
    $sig = \App\Domain\NextAction::signals($conn, $uid);
    if (!isset($sig['claimable_n'], $sig['due_reviews'], $sig['pomo_today'])) smoke_fail('signals shape');
    $na = \App\Domain\NextAction::resolve($conn, $uid);
    if (empty($na['type']) || empty($na['title'])) smoke_fail('resolve shape');
    foreach (['skill_nodes', 'user_skill_mastery', 'mastery_events'] as $t) {
        $chk = $conn->query("SHOW TABLES LIKE '{$t}'");
        if (!$chk || $chk->num_rows === 0) smoke_fail("missing table {$t}");
        if ($chk) $chk->free();
    }
    \App\Domain\Skill\Mastery::award($conn, $uid, 'docker', 10, 'quest', 'quest', $qid);
    \App\Domain\Skill\Mastery::award($conn, $uid, null, 10, 'quest');
    $mm = \App\Domain\Skill\Mastery::map($conn, $uid);
    if (($mm['docker']['xp'] ?? -1) !== 10) smoke_fail('mastery map');
    if (($mm['docker']['level'] ?? 0) !== 1) smoke_fail('mastery level');
    if (\App\Domain\Shop::grantFrame($conn, $uid, 'aurora')) smoke_fail('frame double grant should fail');
    if (!avatar_unlocked('aurora', 1, 0, [], false, \App\Domain\Shop::ownedFrames($conn, $uid))) smoke_fail('frame unlock');

    $conn->rollback();
} catch (Throwable $e) {
    $conn->rollback();
    smoke_fail($e->getMessage());
}

echo "smoke: OK" . PHP_EOL;
