<?php
// Alat: Playground (RPL/DevOps)
$pg_slug = trim($_GET['pg_slug'] ?? ($_SESSION['lab_pg_result']['slug'] ?? 'php-diskon'));
$pg_task = \App\Domain\Playground::find($pg_slug) ?: \App\Domain\Playground::find('php-diskon');
$pg_result = $_SESSION['lab_pg_result'] ?? null;
unset($_SESSION['lab_pg_result']);
$pg_tasks = \App\Domain\Playground::tasks();
?>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($pg_tasks as $t): ?><a class="btn btn-cyber-outline btn-sm<?= $t['slug'] === $pg_task['slug'] ? ' active' : '' ?>" href="<?= lab_url('praktik', 'playground', ['pg_slug' => $t['slug']]) ?>"><?= htmlspecialchars($t['title']) ?> · <?= htmlspecialchars(strtoupper($t['lang'])) ?></a><?php endforeach; ?></div>
<?php if ($pg_result): ?><section class="card p-3 mb-3"><div class="page-kicker"><?= $pg_result['ok'] ? 'Lolos +' . (int)$pg_result['gain'] . ' XP' : 'Belum lolos' ?></div><?php if ($pg_result['msg'] !== ''): ?><p class="small mb-0"><?= htmlspecialchars($pg_result['msg']) ?></p><?php endif; ?><p class="small text-muted mb-0">Expected: <?= htmlspecialchars($pg_task['expected']) ?></p></section><?php endif; ?>
<section class="card p-4 mb-3"><h2 class="h5"><?= htmlspecialchars($pg_task['title']) ?></h2><p class="small text-muted">Expected output: <code><?= htmlspecialchars($pg_task['expected']) ?></code> · <?= htmlspecialchars($pg_task['hint']) ?></p>
<form method="POST" action="<?= lab_url('praktik', 'playground') ?>" id="pgForm"><?= csrf_field() ?><input type="hidden" name="pg_submit" value="1"><input type="hidden" name="pg_slug" value="<?= htmlspecialchars($pg_task['slug']) ?>">
<textarea name="code" id="pgCode" class="form-control font-monospace" rows="8" spellcheck="false"><?= htmlspecialchars($pg_result['code'] ?? $pg_task['starter']) ?></textarea>
<div class="d-flex gap-2 mt-2">
<?php if (($pg_task['lang'] ?? '') === 'js'): ?><button type="button" class="btn btn-cyber-outline btn-sm" id="pgRun">Jalankan di browser</button><?php endif; ?>
<button class="btn btn-cyber btn-sm" type="submit">Kumpulkan</button></div></form>
<?php if (($pg_task['lang'] ?? '') === 'js'): ?><pre id="pgOut" class="small p-2 rounded mt-2 border" style="background:var(--surface-2)">output browser muncul di sini…</pre>
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
