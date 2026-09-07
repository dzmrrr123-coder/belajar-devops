<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$isAdmin = is_admin($conn, $uid);
$isGuru = \App\Domain\Auth\Roles::isGuru($conn, $uid);
if (!$isAdmin && !$isGuru) { http_response_code(404); require __DIR__ . '/404.php'; exit(); }
$squads = [];
try {
    if ($isAdmin) {
        $r = $conn->query("SELECT s.id, s.name, s.code, s.created_by, u.username AS creator, (SELECT COUNT(*) FROM squad_members m WHERE m.squad_id = s.id) AS n FROM squads s JOIN users u ON u.id = s.created_by ORDER BY s.id DESC LIMIT 50");
    } else {
        $s = $conn->prepare("SELECT s.id, s.name, s.code, s.created_by, u.username AS creator, (SELECT COUNT(*) FROM squad_members m WHERE m.squad_id = s.id) AS n FROM squads s JOIN users u ON u.id = s.created_by WHERE s.created_by = ? ORDER BY s.id DESC LIMIT 50");
        if ($s) { $s->bind_param("i", $uid); $s->execute(); $r = $s->get_result(); }
    }
    if (isset($r) && $r) { $squads = $r->fetch_all(MYSQLI_ASSOC); }
} catch (Throwable $e) {}
$focus = max(1, (int)($_GET['squad'] ?? ($squads[0]['id'] ?? 1)));
$allowed = array_map(fn($s) => (int)$s['id'], $squads);
if ($allowed && !in_array($focus, $allowed, true)) $focus = $allowed[0];
$members = [];
try {
    $m = $conn->prepare("SELECT u.id, u.username, u.xp, u.streak, u.track, u.last_active_date, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd, (SELECT COUNT(*) FROM incident_attempts WHERE user_id = u.id) itries, (SELECT COALESCE(AVG(score),0) FROM incident_attempts WHERE user_id = u.id) iavg, (SELECT COUNT(*) FROM reviews WHERE user_id = u.id AND next_due <= CURDATE()) rdue FROM squad_members m JOIN users u ON u.id = m.user_id WHERE m.squad_id = ? ORDER BY u.xp DESC");
    if ($m) { $m->bind_param("i", $focus); $m->execute(); $members = $m->get_result()->fetch_all(MYSQLI_ASSOC); $m->close(); }
} catch (Throwable $e) {}
if (($_GET['format'] ?? '') === 'csv' && $allowed && in_array($focus, $allowed, true)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kelas-' . $focus . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['username', 'track', 'xp', 'streak', 'quest_done', 'incident_tries', 'incident_avg', 'review_due', 'last_active']);
    foreach ($members as $mb) fputcsv($o, [$mb['username'], $mb['track'] ?? 'devops', $mb['xp'], $mb['streak'], $mb['qd'], $mb['itries'], (int)$mb['iavg'], $mb['rdue'], $mb['last_active_date'] ?? '']);
    fclose($o); $conn->close(); exit();
}
$conn->close();
$page_title = 'Kelas · Dashboard Guru';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">Sekolah · <?= count($squads) ?> kelas</div>
<h1 class="page-title">Dashboard kelas</h1>
<p class="page-desc">Progres siswa per kelas: quest, incident, review due, aktivitas.</p>
<form method="GET" action="kelas.php" class="d-flex gap-2 mt-2" style="max-width:420px">
<label class="small text-muted mb-0 align-self-center" for="squadSel">Kelas:</label>
<select id="squadSel" name="squad" class="form-select form-select-sm" onchange="this.form.submit()">
<?php foreach ($squads as $sq): ?>
<option value="<?= (int)$sq['id'] ?>" <?= (int)$sq['id'] === $focus ? 'selected' : '' ?>><?= htmlspecialchars($sq['name']) ?> (<?= (int)$sq['n'] ?> siswa)</option>
<?php endforeach; ?>
</select>
<?php if ($allowed && in_array($focus, $allowed, true)): ?><a href="kelas.php?squad=<?= $focus ?>&format=csv" class="btn btn-cyber-outline btn-sm flex-shrink-0">CSV</a><?php endif; ?>
</form></div>
<?php if (!$squads): ?><div class="card p-4"><p class="small text-muted mb-0">Belum ada kelas. Buat squad dulu, siswa gabung pakai kode.</p></div><?php endif; ?>
<section class="card p-2">
<?php foreach ($members as $mb): $gap = ((int)($mb['itries'] ?? 0) === 0) ? 'belum coba incident' : (((int)($mb['iavg'] ?? 0) < 70) ? 'incident avg rendah' : 'aman'); ?>
<div class="list-row"><span class="avatar-circle avatar-sm" aria-hidden="true"><?= strtoupper(substr($mb['username'], 0, 1)) ?></span>
<div class="list-main"><p class="list-title"><?= htmlspecialchars($mb['username']) ?> · <?= (int)$mb['xp'] ?> XP · <?= htmlspecialchars($mb['track'] ?? 'devops') ?></p><p class="list-meta"><?= (int)($mb['qd'] ?? 0) ?> quest · <?= (int)($mb['itries'] ?? 0) ?> incident (avg <?= (int)($mb['iavg'] ?? 0) ?>) · <?= (int)($mb['rdue'] ?? 0) ?> review due · aktif <?= htmlspecialchars($mb['last_active_date'] ?? '-') ?> · gap: <?= $gap ?></p></div></div>
<?php endforeach; ?>
<?php if ($squads && !$members): ?><p class="small text-muted p-3 mb-0">Belum ada siswa di kelas ini. Bagikan kode squad.</p><?php endif; ?>
</section>
</main>
<?php require_once 'includes/footer.php'; ?>
