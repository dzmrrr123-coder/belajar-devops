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

    $conn->rollback();
} catch (Throwable $e) {
    $conn->rollback();
    smoke_fail($e->getMessage());
}

echo "smoke: OK" . PHP_EOL;
