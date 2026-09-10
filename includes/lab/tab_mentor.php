<?php
// Tab Mentor: rekomendasi adaptif (read-only)
$mt_sig = \App\Domain\NextAction::signals($conn, $uid);
$mt_due = (int)($mt_sig['due_reviews'] ?? 0);
$mt_tries = 0; $mt_avg = 0; $mt_gap = ''; $mt_qdone = 0; $mt_qtotal = 1;
try {
    $q = $conn->prepare("SELECT COUNT(*) c, COALESCE(AVG(score),0) a FROM incident_attempts WHERE user_id = ?");
    if ($q) { $q->bind_param("i", $uid); $q->execute(); $r = $q->get_result()->fetch_assoc() ?: []; $q->close(); $mt_tries = (int)($r['c'] ?? 0); $mt_avg = (int)($r['a'] ?? 0); }
    $g = $conn->prepare("SELECT skill, COUNT(*) c FROM errors WHERE user_id = ? GROUP BY skill ORDER BY c ASC LIMIT 1");
    if ($g) { $g->bind_param("i", $uid); $g->execute(); $mt_gap = (string)($g->get_result()->fetch_assoc()['skill'] ?? ''); $g->close(); }
    $qd = $conn->prepare("SELECT COUNT(*) c FROM user_quests WHERE user_id = ?");
    if ($qd) { $qd->bind_param("i", $uid); $qd->execute(); $mt_qdone = (int)($qd->get_result()->fetch_assoc()['c'] ?? 0); $qd->close(); }
    $qt = $conn->prepare("SELECT COUNT(*) c FROM quests WHERE user_id IS NULL AND (track = ? OR track = 'all')");
    if ($qt) { $qt->bind_param("s", $track); $qt->execute(); $mt_qtotal = max(1, (int)($qt->get_result()->fetch_assoc()['c'] ?? 1)); $qt->close(); }
} catch (Throwable $e) {}
$mt_recs = \App\Domain\Mentor::recommend(['track' => $track, 'due_reviews' => $mt_due, 'incident_tries' => $mt_tries, 'incident_avg' => $mt_avg, 'skill_gap' => $mt_gap, 'streak_broken' => !empty($mt_sig['streak_broken']), 'quest_pct' => ($mt_qdone / $mt_qtotal * 100)]);
?>
<p class="page-desc">Rekomendasi adaptif berdasarkan track <strong><?= htmlspecialchars(strtoupper($track)) ?></strong>, aktivitas harian, dan penguasaan kompetensi.</p>
<div class="row g-3">
<?php foreach ($mt_recs as $r): ?>
<div class="col-md-4"><div class="card p-4 h-100"><i class="<?= htmlspecialchars($r['icon']) ?>"></i><h2 class="h5 mt-2"><?= htmlspecialchars($r['title']) ?></h2><p class="small text-muted"><?= htmlspecialchars($r['desc']) ?></p><a class="btn btn-cyber btn-sm w-100" href="<?= htmlspecialchars($r['href']) ?>"><?= htmlspecialchars($r['cta']) ?></a></div></div>
<?php endforeach; ?>
</div>
