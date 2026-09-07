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
$recentByMember = [];
$rubAvg = [];
$rubric_criteria = \App\Domain\Dkv\Rubric::criteria();
try {
    $uids = array_map(fn($m) => (int)$m['id'], $members);
    if ($uids) {
        $in = implode(',', $uids);
        $r = $conn->query("SELECT uq.user_id, uq.quest_id, q.title FROM user_quests uq JOIN quests q ON q.id = uq.quest_id WHERE uq.user_id IN ($in) ORDER BY uq.created_at DESC LIMIT 120");
        if ($r) {
            $cnt = [];
            foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) {
                $mu = (int)$row['user_id'];
                $cnt[$mu] = ($cnt[$mu] ?? 0) + 1;
                if ($cnt[$mu] > 3) continue;
                $recentByMember[$mu][] = ['quest_id' => (int)$row['quest_id'], 'title' => (string)$row['title']];
            }
            $r->free();
        }
        foreach ($recentByMember as $mu => $qs) {
            $sums = \App\Domain\Dkv\Rubric::summaries($conn, $mu, array_map(fn($q) => $q['quest_id'], $qs));
            foreach ($sums as $qid => $avg) $rubAvg[$mu . ':' . $qid] = $avg;
        }
    }
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
<?php foreach ($members as $mb): $gap = ((int)($mb['itries'] ?? 0) === 0) ? 'belum coba incident' : (((int)($mb['iavg'] ?? 0) < 70) ? 'incident avg rendah' : 'aman'); $mid = (int)$mb['id']; ?>
<div class="list-row"><span class="avatar-circle avatar-sm" aria-hidden="true"><?= strtoupper(substr($mb['username'], 0, 1)) ?></span>
<div class="list-main"><p class="list-title"><?= htmlspecialchars($mb['username']) ?> · <?= (int)$mb['xp'] ?> XP · <?= htmlspecialchars($mb['track'] ?? 'devops') ?></p><p class="list-meta"><?= (int)($mb['qd'] ?? 0) ?> quest · <?= (int)($mb['itries'] ?? 0) ?> incident (avg <?= (int)($mb['iavg'] ?? 0) ?>) · <?= (int)($mb['rdue'] ?? 0) ?> review due · aktif <?= htmlspecialchars($mb['last_active_date'] ?? '-') ?> · gap: <?= $gap ?></p>
<?php foreach ($recentByMember[$mid] ?? [] as $rq): $rk = $mid . ':' . $rq['quest_id']; $ra = $rubAvg[$rk] ?? null; ?>
<details class="small mt-1"><summary style="cursor:pointer">Nilai: <?= htmlspecialchars(mb_strimwidth($rq['title'], 0, 50, '...')) ?><?= $ra !== null ? ' · ' . htmlspecialchars((string)$ra) . '/5' : '' ?></summary>
<form method="POST" action="rubric.php" class="d-flex flex-wrap gap-1 mt-1 m-0"><?= csrf_field() ?>
<input type="hidden" name="quest_id" value="<?= (int)$rq['quest_id'] ?>"><input type="hidden" name="owner_id" value="<?= $mid ?>">
<?php foreach ($rubric_criteria as $rc): ?><label class="small text-muted mb-0"><?= htmlspecialchars($rc['name']) ?><select name="score[<?= htmlspecialchars($rc['slug']) ?>]" class="form-select form-select-sm" style="max-width:64px" aria-label="<?= htmlspecialchars($rc['name']) ?>"><?php for ($sv = 1; $sv <= 5; $sv++): ?><option value="<?= $sv ?>" <?= $sv === 4 ? 'selected' : '' ?>><?= $sv ?></option><?php endfor; ?></select></label><?php endforeach; ?>
<input name="note" class="form-control form-control-sm" maxlength="300" placeholder="Critique singkat…" aria-label="Critique guru" style="max-width:220px">
<button class="btn btn-cyber-outline btn-sm" type="submit">Nilai</button></form>
<form method="POST" action="critique.php" class="d-flex gap-1 mt-1 m-0"><?= csrf_field() ?><input type="hidden" name="quest_id" value="<?= (int)$rq['quest_id'] ?>"><input type="hidden" name="owner_id" value="<?= $mid ?>"><input name="note" class="form-control form-control-sm" maxlength="500" placeholder="Balas critique…" aria-label="Balas critique" style="max-width:220px"><button class="btn btn-cyber-outline btn-sm" type="submit">Balas</button></form>
<?php foreach (\App\Domain\Dkv\Critique::thread($conn, $mid, (int)$rq['quest_id'], 3) as $cm): ?><p class="small text-muted mb-1"><?= htmlspecialchars($cm['username']) ?>: <?= htmlspecialchars($cm['note']) ?></p><?php endforeach; ?>
</details>
<?php endforeach; ?>
</div></div>
<?php endforeach; ?>
<?php if ($squads && !$members): ?><p class="small text-muted p-3 mb-0">Belum ada siswa di kelas ini. Bagikan kode squad.</p><?php endif; ?>
</section>
</main>
<?php require_once 'includes/footer.php'; ?>
