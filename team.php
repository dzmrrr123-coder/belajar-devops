<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
try { @$conn->query("CREATE TABLE IF NOT EXISTS `team_assignments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `squad_id` INT NOT NULL, `challenge_id` INT NOT NULL, `due_at` DATE NULL, `created_by` INT NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (Throwable $e) {}
$sid = \App\Domain\Social\Squads::mySquadId($conn, $uid);
if ($sid === null) { $conn->close(); set_flash('info', 'Gabung atau buat squad dulu untuk buka dashboard tim.'); redirect('squad.php'); }
$squad = \App\Domain\Social\Squads::detail($conn, $sid);
$isLead = ($squad && (int)($squad['created_by'] ?? 0) === $uid) || is_admin($conn, $uid);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLead) {
    verify_csrf();
    if (rate_limit_hit('team_assign', 10, 3600)) { set_flash('warning', 'Terlalu sering.'); redirect('team.php'); }
    $cid = (int)($_POST['challenge_id'] ?? 0);
    $due = trim($_POST['due_at'] ?? '');
    $dueSql = $due !== '' ? $due : null;
    $ins = $conn->prepare("INSERT INTO team_assignments (squad_id, challenge_id, due_at, created_by) VALUES (?, ?, ?, ?)");
    if ($ins) { $ins->bind_param("iisi", $sid, $cid, $dueSql, $uid); $ins->execute(); $ins->close(); set_flash('success', 'Assignment ditambah.'); }
    redirect('team.php');
}
$memberIds = array_map(fn($m) => (int)$m['id'], $squad['members'] ?? []);
$stats = [];
if ($memberIds) {
    $ph = implode(',', array_fill(0, count($memberIds), '?'));
    $types = str_repeat('i', count($memberIds));
    try {
        $s = $conn->prepare("SELECT u.id, u.username, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd, (SELECT COUNT(*) FROM incident_attempts WHERE user_id = u.id) itries, (SELECT COALESCE(AVG(score),0) FROM incident_attempts WHERE user_id = u.id) iavg, (SELECT COUNT(*) FROM reviews WHERE user_id = u.id AND next_due <= CURDATE()) rdue, u.last_active_date FROM users u WHERE u.id IN ($ph)");
        if ($s) { $s->bind_param($types, ...$memberIds); $s->execute(); foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $stats[(int)$r['id']] = $r; $s->close(); }
    } catch (Throwable $e) {}
}
$assigns = [];
try {
    $s = $conn->prepare("SELECT a.id, a.challenge_id, a.due_at, c.slug, c.title, c.skill FROM team_assignments a JOIN incident_challenges c ON c.id = a.challenge_id WHERE a.squad_id = ? ORDER BY a.id DESC LIMIT 20");
    if ($s) { $s->bind_param("i", $sid); $s->execute(); $assigns = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close(); }
} catch (Throwable $e) {}
$doneMap = [];
if ($assigns && $memberIds) {
    $ph = implode(',', array_fill(0, count($memberIds), '?'));
    $types = str_repeat('i', count($memberIds));
    try {
        $s = $conn->prepare("SELECT challenge_id, user_id, MAX(score) best FROM incident_attempts WHERE user_id IN ($ph) GROUP BY challenge_id, user_id");
        if ($s) { $s->bind_param($types, ...$memberIds); $s->execute(); foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $doneMap[(int)$r['challenge_id'] . ':' . (int)$r['user_id']] = (int)$r['best']; $s->close(); }
    } catch (Throwable $e) {}
}
$chall = [];
try {
    $r = $conn->query("SELECT id, title, skill FROM incident_challenges ORDER BY id ASC");
    if ($r) { $chall = $r->fetch_all(MYSQLI_ASSOC); $r->free(); }
} catch (Throwable $e) {}
if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="team-' . $sid . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['username', 'quest_done', 'incident_tries', 'incident_avg', 'review_due', 'last_active']);
    foreach ($squad['members'] as $m) { $st = $stats[(int)$m['id']] ?? []; fputcsv($o, [$m['username'], $st['qd'] ?? 0, $st['itries'] ?? 0, (int)($st['iavg'] ?? 0), $st['rdue'] ?? 0, $st['last_active_date'] ?? '']); }
    fclose($o); $conn->close(); exit();
}
$conn->close();
$page_title = 'Dashboard Tim';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">B2B starter · <?= htmlspecialchars($squad['name'] ?? '') ?> · <?= count($squad['members'] ?? []) ?> anggota</div>
<h1 class="page-title">Dashboard tim</h1>
<p class="page-desc">Skill gap + assignment + laporan. Lead: <?= $isLead ? 'kamu' : htmlspecialchars($squad['creator'] ?? '') ?>.</p>
<div class="d-flex gap-2 flex-wrap"><a href="team.php?format=csv" class="btn btn-cyber-outline btn-sm">Export CSV</a><a href="squad.php" class="btn btn-cyber-outline btn-sm">Kelola squad</a></div></div>
<section class="card p-2 mb-3">
<?php foreach ($squad['members'] as $m): $st = $stats[(int)$m['id']] ?? []; $gap = ((int)($st['itries'] ?? 0) === 0) ? 'belum coba incident' : (((int)($st['iavg'] ?? 0) < 70) ? 'incident avg rendah' : 'aman'); ?>
<div class="list-row"><span class="avatar-circle avatar-sm" aria-hidden="true"><?= strtoupper(substr($m['username'], 0, 1)) ?></span>
<div class="list-main"><p class="list-title"><?= htmlspecialchars($m['username']) ?> · +<?= (int)$m['wxp'] ?> XP minggu ini</p><p class="list-meta"><?= (int)($st['qd'] ?? 0) ?> quest · <?= (int)($st['itries'] ?? 0) ?> incident (avg <?= (int)($st['iavg'] ?? 0) ?>) · <?= (int)($st['rdue'] ?? 0) ?> review due · gap: <?= $gap ?></p></div></div>
<?php endforeach; ?>
</section>
<section class="card p-4 mb-3"><h2 class="h6 fw-bold mb-2">Assignment (<?= count($assigns) ?>)</h2>
<?php if ($isLead): ?><form method="POST" class="row g-2 mb-3"><?= csrf_field() ?>
<div class="col-md-6"><select name="challenge_id" class="form-select"><?php foreach ($chall as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['skill']) ?>)</option><?php endforeach; ?></select></div>
<div class="col-md-3"><input type="date" name="due_at" class="form-control"></div>
<div class="col-md-3"><button class="btn btn-cyber btn-sm w-100" type="submit">Assign</button></div></form><?php endif; ?>
<?php foreach ($assigns as $a): $acid = (int)$a['challenge_id']; ?>
<div class="list-row"><div class="list-main"><p class="list-title"><a href="incident.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a></p><p class="list-meta"><?= htmlspecialchars($a['skill']) ?><?= !empty($a['due_at']) ? ' · due ' . htmlspecialchars($a['due_at']) : '' ?></p></div></div>
<div class="d-flex flex-wrap gap-2 mb-2 small"><?php foreach ($squad['members'] as $m): $b = $doneMap[$acid . ':' . (int)$m['id']] ?? null; ?><span><?= htmlspecialchars($m['username']) ?>: <?= $b !== null ? '<strong>' . (int)$b . '</strong>' : 'belum' ?></span><?php endforeach; ?></div>
<?php endforeach; ?>
<?php if (!$assigns): ?><p class="small text-muted mb-0">Belum ada assignment. Lead bisa assign 1 lab sebagai tugas tim.</p><?php endif; ?>
</section>
</main>
<?php require_once 'includes/footer.php'; ?>
