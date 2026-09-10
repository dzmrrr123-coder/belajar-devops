<?php
// Alat: Lab Soal (bank soal per track)
$lb_slug = trim($_GET['lab_slug'] ?? '');
$lb_active = $lb_slug !== '' ? \App\Domain\Lab\LabBank::find($lb_slug) : null;
$lb_mine = \App\Domain\Lab\LabBank::forTrack($track);
$lb_others = array_values(array_filter(\App\Domain\Lab\LabBank::all(), fn($l) => $l['track'] !== $track));
if (!$lb_active && !empty($lb_mine)) $lb_active = $lb_mine[0];
$lb_result = $_SESSION['lab_bank_result'] ?? null;
unset($_SESSION['lab_bank_result']);
?>
<div class="row g-4 align-items-stretch">
    <div class="col-lg-7 d-flex flex-column">
        <?php if ($lb_active): ?>
        <section class="card h-100 p-0 overflow-hidden shadow-sm" style="border-radius: 12px;">
            <div class="p-2 border-bottom d-flex align-items-center gap-2" style="background:var(--surface-2)">
                <span class="rounded-circle bg-danger" style="width:12px; height:12px;"></span>
                <span class="rounded-circle bg-warning" style="width:12px; height:12px;"></span>
                <span class="rounded-circle bg-success" style="width:12px; height:12px;"></span>
                <span class="ms-2 small font-monospace text-secondary">user@learntracker:~/$ <?= htmlspecialchars($lb_active['slug']) ?></span>
                <div class="ms-auto d-flex gap-2">
                    <span class="badge bg-secondary-subtle text-secondary"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($lb_active['skill']) ?></span>
                    <span class="badge bg-warning-subtle text-warning-emphasis"><i class="fas fa-bolt me-1"></i>+<?= (int)$lb_active['xp'] ?> XP</span>
                </div>
            </div>
            <div class="p-4 flex-grow-1 font-monospace small d-flex flex-column" style="line-height: 1.6;">
                <h2 class="text-primary h5 fw-bold mb-3">> <?= htmlspecialchars($lb_active['title']) ?></h2>
                <div class="text-secondary mb-3 fs-6" style="white-space: pre-wrap;"><?= htmlspecialchars($lb_active['prompt']) ?></div>
                <?php if (!empty($lb_active['code'])): ?>
                    <pre class="p-3 rounded border mt-auto mb-0" style="font-size: 0.85rem; background:var(--surface-2)"><code><?= htmlspecialchars($lb_active['code']) ?></code></pre>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
    <div class="col-lg-5 d-flex flex-column gap-3">
        <?php if ($lb_result): ?>
        <section class="card p-3 border-<?= $lb_result['ok'] ? 'success' : 'warning' ?>">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="fas <?= $lb_result['ok'] ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> fs-5"></i>
                <strong class="<?= $lb_result['ok'] ? 'text-success' : 'text-warning' ?>"><?= $lb_result['ok'] ? 'Jawaban Benar!' : 'Belum Tepat' ?></strong>
                <?php if ($lb_result['gain'] > 0): ?>
                    <span class="badge bg-success-subtle text-success ms-auto">+<?= (int)$lb_result['gain'] ?> XP</span>
                <?php endif; ?>
            </div>
            <p class="small text-muted mb-0"><?= htmlspecialchars($lb_result['lab']['explanation']) ?></p>
        </section>
        <?php endif; ?>
        <?php if ($lb_active): ?>
        <section class="card p-4 shadow-sm border-0 bg-body-tertiary">
            <h3 class="h6 fw-bold mb-3"><i class="fas fa-keyboard me-2"></i>Kontrol Misi</h3>
            <form method="POST" action="<?= lab_url('praktik', 'lab') ?>" class="d-flex flex-column gap-3 m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="lab_submit" value="1">
                <input type="hidden" name="lab_slug" value="<?= htmlspecialchars($lb_active['slug']) ?>">
                <?php if (($lb_active['type'] ?? 'mcq') === 'calc'): ?>
                    <div class="input-group">
                        <input name="answer" class="form-control" placeholder="Input output/angka..." inputmode="numeric" required autocomplete="off">
                        <button class="btn btn-cyber" type="submit">Jalankan</button>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($lb_active['options'] as $i => $o): ?>
                            <label class="d-flex gap-3 align-items-center p-2 px-3 rounded border border-secondary-subtle bg-surface" style="cursor: pointer;">
                                <input type="radio" name="answer" value="<?= $i ?>" required class="form-check-input mt-0">
                                <span class="small font-monospace"><?= htmlspecialchars($o) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-cyber w-100 mt-2" type="submit"><i class="fas fa-play me-2"></i>Jalankan Kueri</button>
                <?php endif; ?>
            </form>
        </section>
        <?php endif; ?>
        <section class="card p-4 shadow-sm border-0">
            <h3 class="h6 fw-bold mb-3"><i class="fas fa-layer-group me-2"></i>Soal Lab (<?= count($lb_mine) ?>)</h3>
            <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                <?php foreach ($lb_mine as $l): $isActive = ($lb_active && $lb_active['slug'] === $l['slug']); ?>
                    <a class="list-row <?= $isActive ? 'active bg-primary bg-opacity-10 border-primary' : '' ?>" href="<?= lab_url('praktik', 'lab', ['lab_slug' => $l['slug']]) ?>" style="padding: 10px 12px; text-decoration: none;">
                        <div class="list-main">
                            <p class="list-title <?= $isActive ? 'text-primary' : '' ?>"><?= htmlspecialchars($l['title']) ?></p>
                            <p class="list-meta"><?= htmlspecialchars($l['skill']) ?></p>
                        </div>
                        <?php if ($isActive): ?><i class="fas fa-chevron-right text-primary small"></i><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($lb_others)): ?>
            <div class="mt-3 pt-3 border-top">
                <details>
                    <summary class="small text-muted fw-bold" style="cursor: pointer;"><i class="fas fa-compass me-1"></i> Eksplorasi Jurusan Lain (<?= count($lb_others) ?>)</summary>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <?php foreach ($lb_others as $l): ?>
                            <a class="badge bg-secondary-subtle text-secondary text-decoration-none <?= ($lb_active && $lb_active['slug'] === $l['slug']) ? 'border border-primary' : '' ?>" href="<?= lab_url('praktik', 'lab', ['lab_slug' => $l['slug']]) ?>">
                                <?= htmlspecialchars(strtoupper($l['track'])) ?>: <?= htmlspecialchars($l['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>
