<?php
// Alat: Terminal Linux (TKJ/DevOps)
$tm_slug = trim($_GET['tm_m'] ?? 'fix-www');
$tm_mission = \App\Domain\Tkj\Terminal::find($tm_slug) ?: \App\Domain\Tkj\Terminal::find('fix-www');
$tm_st = $_SESSION['term_' . $tm_mission['slug']] ?? [];
$tm_missions = \App\Domain\Tkj\Terminal::missions();
?>
<div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($tm_missions as $m): ?><a class="btn btn-cyber-outline btn-sm<?= $m['slug'] === $tm_mission['slug'] ? ' active' : '' ?>" href="<?= lab_url('praktik', 'terminal', ['tm_m' => $m['slug']]) ?>"><?= htmlspecialchars($m['title']) ?></a><?php endforeach; ?></div>
<p class="page-desc">Misi: <?= htmlspecialchars($tm_mission['title']) ?> — <?= htmlspecialchars($tm_mission['goal']) ?></p>
<?php if (!empty($tm_st['claimed'])): ?><div class="card p-3 mb-3"><div class="page-kicker">Misi selesai · klaim XP harian otomatis</div></div><?php endif; ?>
<section class="card p-3 mb-3"><div class="small font-monospace rounded p-3" style="min-height:180px; background:var(--surface-2)" aria-live="polite">
<?php foreach (($tm_st['log'] ?? []) as $ln): ?><div><?= htmlspecialchars($ln[0]) ?></div><div class="text-secondary"><?= htmlspecialchars($ln[1]) ?></div><?php endforeach; ?>
<?php if (empty($tm_st['log'])): ?><div class="text-secondary">ketik help lalu mulai misi…</div><?php endif; ?>
</div>
<form method="POST" action="<?= lab_url('praktik', 'terminal') ?>" class="d-flex gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="tm_op" value="exec"><input type="hidden" name="tm_m" value="<?= htmlspecialchars($tm_mission['slug']) ?>"><span class="text-success font-monospace">$</span><input name="tm_cmd" class="form-control form-control-sm font-monospace" autocomplete="off" placeholder="help" aria-label="Perintah terminal"><button class="btn btn-cyber btn-sm" type="submit">Run</button></form>
<form method="POST" action="<?= lab_url('praktik', 'terminal') ?>" class="mt-2"><?= csrf_field() ?><input type="hidden" name="tm_op" value="reset"><input type="hidden" name="tm_m" value="<?= htmlspecialchars($tm_mission['slug']) ?>"><button class="btn btn-cyber-outline btn-sm" type="submit">Reset sesi</button></form></section>
