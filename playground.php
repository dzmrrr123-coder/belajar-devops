<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
enforce_track_access($conn, $uid, ['rpl', 'devops'], 'Coding Playground (PHP / JS / SQL)');
define('PG_CAP', 30);
$result = null;
$slug = trim($_GET['slug'] ?? ($_POST['slug'] ?? 'php-diskon'));
$task = \App\Domain\Playground::find($slug) ?: \App\Domain\Playground::find('php-diskon');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (rate_limit_hit('pg_submit', 20, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect('playground.php?slug=' . urlencode($slug)); }
    $code = substr((string)($_POST['code'] ?? ''), 0, 2000);
    $ok = \App\Domain\Playground::grade($task['slug'], $code);
    $gain = 0;
    $msg = '';
    if (($task['lang'] ?? '') === 'php') {
        $r = \App\Domain\Playground::safePhpOutput($code);
        $msg = $r['ok'] ? ('output: ' . $r['output']) : $r['msg'];
    }
    if ($ok) {
        $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'playground' AND amount > 0 AND created_at >= CURDATE()");
        $sum = 0;
        if ($c) { $c->bind_param("i", $uid); $c->execute(); $sum = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close(); }
        $gain = capped_xp_gain((int)$task['xp'], $sum, PG_CAP);
        if ($gain > 0) {
            award_xp($conn, $uid, $gain, 'playground', 'playground', crc32($task['slug']) % 100000);
            \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$task['skill']), 5, 'playground', 'playground', crc32($task['slug']) % 100000);
        }
        check_and_unlock_badges($conn, $uid);
    }
    $result = ['ok' => $ok, 'gain' => $gain, 'msg' => $msg, 'code' => $code];
}
$tasks = \App\Domain\Playground::tasks();
$conn->close();
$page_title = 'Playground';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">RPL · eksekusi nyata + aman</div>
<h1 class="page-title">Playground</h1>
<p class="page-desc">PHP dievaluasi aman di server (tanpa eval). JS jalan di browser lalu diverifikasi. SQL difilter ke dataset. Cap <?= PG_CAP ?> XP/hari.</p></div>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($tasks as $t): ?><a class="btn btn-cyber-outline btn-sm<?= $t['slug'] === $task['slug'] ? ' active' : '' ?>" href="playground.php?slug=<?= urlencode($t['slug']) ?>"><?= htmlspecialchars($t['title']) ?> · <?= htmlspecialchars(strtoupper($t['lang'])) ?></a><?php endforeach; ?></div>
<?php if ($result): ?><section class="card p-3 mb-3"><div class="page-kicker"><?= $result['ok'] ? 'Lolos +' . (int)$result['gain'] . ' XP' : 'Belum lolos' ?></div><?php if ($result['msg'] !== ''): ?><p class="small mb-0"><?= htmlspecialchars($result['msg']) ?></p><?php endif; ?><p class="small text-muted mb-0">Expected: <?= htmlspecialchars($task['expected']) ?></p></section><?php endif; ?>
<section class="card p-4 mb-3"><h2 class="h5"><?= htmlspecialchars($task['title']) ?></h2><p class="small text-muted">Expected output: <code><?= htmlspecialchars($task['expected']) ?></code> · <?= htmlspecialchars($task['hint']) ?></p>
<form method="POST" id="pgForm"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= htmlspecialchars($task['slug']) ?>">
<textarea name="code" id="pgCode" class="form-control font-monospace" rows="8" spellcheck="false"><?= htmlspecialchars($result['code'] ?? $task['starter']) ?></textarea>
<div class="d-flex gap-2 mt-2">
<?php if (($task['lang'] ?? '') === 'js'): ?><button type="button" class="btn btn-cyber-outline btn-sm" id="pgRun">Jalankan di browser</button><?php endif; ?>
<button class="btn btn-cyber btn-sm" type="submit">Kumpulkan</button></div></form>
<?php if (($task['lang'] ?? '') === 'js'): ?><pre id="pgOut" class="small p-2 bg-dark text-light rounded mt-2">output browser muncul di sini…</pre>
<script>
document.getElementById('pgRun').onclick = () => {
  const code = document.getElementById('pgCode').value;
  const logs = [];
  const console = { log: (...a) => logs.push(a.join(' ')) };
  try { new Function('console', code)(console); document.getElementById('pgOut').textContent = logs.join('\n') || '(kosong — pakai console.log)'; }
  catch (e) { document.getElementById('pgOut').textContent = 'Error: ' + e.message; }
};
</script><?php endif; ?>
</section>
</main>
<?php require_once 'includes/footer.php'; ?>
