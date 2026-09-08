<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
enforce_track_access($conn, $uid, ['tkj', 'devops'], 'Terminal Linux Lab');
define('TERM_CAP', 30);
$slug = trim($_GET['m'] ?? ($_POST['m'] ?? 'fix-www'));
$mission = \App\Domain\Tkj\Terminal::find($slug) ?: \App\Domain\Tkj\Terminal::find('fix-www');
$st = $_SESSION['term_' . $mission['slug']] ?? [];
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'reset') { unset($_SESSION['term_' . $mission['slug']]); redirect('terminal.php?m=' . urlencode($mission['slug'])); }
    if (rate_limit_hit('term_cmd', 60, 3600)) { set_flash('warning', 'Terlalu sering.'); redirect('terminal.php?m=' . urlencode($mission['slug'])); }
    $r = \App\Domain\Tkj\Terminal::exec($st, (string)($_POST['cmd'] ?? ''));
    $st = $r['state'];
    $_SESSION['term_' . $mission['slug']] = $st;
    if (\App\Domain\Tkj\Terminal::missionDone($st, $mission) && empty($st['claimed'])) {
        $st['claimed'] = true;
        $_SESSION['term_' . $mission['slug']] = $st;
        $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'terminal' AND amount > 0 AND created_at >= CURDATE()");
        $sum = 0;
        if ($c) { $c->bind_param("i", $uid); $c->execute(); $sum = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close(); }
        $gain = capped_xp_gain((int)$mission['xp'], $sum, TERM_CAP);
        if ($gain > 0) {
            award_xp($conn, $uid, $gain, 'terminal', 'terminal', crc32($mission['slug']) % 100000);
            \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$mission['skill']), 5, 'terminal', 'terminal', crc32($mission['slug']) % 100000);
        }
        check_and_unlock_badges($conn, $uid);
        $result = ['done' => true, 'gain' => $gain];
    }
    redirect('terminal.php?m=' . urlencode($mission['slug']));
}
$missions = \App\Domain\Tkj\Terminal::missions();
$conn->close();
$page_title = 'Terminal Linux';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">TKJ · terminal virtual aman</div>
<h1 class="page-title">Terminal lab</h1><p class="page-desc">Misi: <?= htmlspecialchars($mission['title']) ?> — <?= htmlspecialchars($mission['goal']) ?></p></div>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($missions as $m): ?><a class="btn btn-cyber-outline btn-sm<?= $m['slug'] === $mission['slug'] ? ' active' : '' ?>" href="terminal.php?m=<?= urlencode($m['slug']) ?>"><?= htmlspecialchars($m['title']) ?></a><?php endforeach; ?></div>
<?php if (!empty($st['claimed'])): ?><div class="card p-3 mb-3"><div class="page-kicker">Misi selesai · klaim XP harian otomatis</div></div><?php endif; ?>
<section class="card p-3 mb-3 bg-dark text-light"><div class="small font-monospace" style="min-height:180px" aria-live="polite">
<?php foreach (($st['log'] ?? []) as $ln): ?><div><?= htmlspecialchars($ln[0]) ?></div><div class="text-secondary"><?= htmlspecialchars($ln[1]) ?></div><?php endforeach; ?>
<?php if (empty($st['log'])): ?><div class="text-secondary">ketik help lalu mulai misi…</div><?php endif; ?>
</div>
<form method="POST" class="d-flex gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="m" value="<?= htmlspecialchars($mission['slug']) ?>"><span class="text-success font-monospace">$</span><input name="cmd" class="form-control form-control-sm font-monospace bg-dark text-light" autocomplete="off" placeholder="help" aria-label="Perintah terminal"><button class="btn btn-cyber btn-sm" type="submit">Run</button></form>
<form method="POST" class="mt-2"><?= csrf_field() ?><input type="hidden" name="m" value="<?= htmlspecialchars($mission['slug']) ?>"><input type="hidden" name="action" value="reset"><button class="btn btn-cyber-outline btn-sm" type="submit">Reset sesi</button></form></section>
</main>
<?php require_once 'includes/footer.php'; ?>
