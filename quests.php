<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$myTrack = user_track($conn, $user_id);
$trackName = \App\Domain\Track\Tracks::all()[$myTrack]['name'] ?? 'DevOps';
try { \App\Domain\Track\Roadmap::ensureSeed($conn); } catch (Throwable $e) {}
$inClass = false;
try {
    $sc = $conn->prepare("SELECT COUNT(*) c FROM squad_members WHERE user_id = ?");
    if ($sc) { $sc->bind_param("i", $user_id); $sc->execute(); $inClass = ((int)($sc->get_result()->fetch_assoc()['c'] ?? 0)) > 0; $sc->close(); }
} catch (Throwable $e) {}
$canSwitchTrack = !$inClass || is_admin($conn, $user_id) || \App\Domain\Auth\Roles::isGuru($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_custom') {
        $title = mb_substr(clean($_POST['title'] ?? ''), 0, 255);
        $desc = mb_substr(clean($_POST['description'] ?? ''), 0, 2000);
        $week = max(1, min(12, (int)($_POST['week'] ?? 1)));
        $xp = max(5, min(20, (int)($_POST['xp_reward'] ?? 10)));
        if ($title === '') set_flash('warning', 'Judul quest wajib diisi.');
        else {
            $stmt = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward, track) VALUES (?, 1, ?, ?, ?, ?, ?)");
            if ($stmt) $stmt->bind_param("iissis", $user_id, $week, $title, $desc, $xp, $myTrack);
            else { $stmt = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward) VALUES (?, 1, ?, ?, ?, ?)"); $stmt->bind_param("iissi", $user_id, $week, $title, $desc, $xp); }
            $ok = $stmt->execute();
            $stmt->close();
            $nb = $ok ? check_and_unlock_badges($conn, $user_id) : [];
            set_flash($ok ? 'success' : 'danger', $ok ? 'Quest custom dibuat.' . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : '') : 'Gagal membuat quest.');
        }
        redirect('quests.php');
    }
    if ($action === 'delete_custom') {
        $qid = (int)($_POST['quest_id'] ?? 0);
        delete_review($conn, $user_id, 'quest', $qid);
        $stmt = $conn->prepare("DELETE FROM quests WHERE id = ? AND user_id = ? AND is_custom = 1");
        $stmt->bind_param("ii", $qid, $user_id);
        $stmt->execute();
        $stmt->close();
        set_flash('info', 'Quest custom dihapus.');
        redirect('quests.php');
    }
}

// Get all quests with user completion status (global track aktif + custom milik sendiri pada track aktif)
$stmt = $conn->prepare("
    SELECT q.id, q.user_id, q.week, q.title, q.description, q.xp_reward, q.is_custom, q.depends_on, uq.completed_at
    FROM quests q
    LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
    WHERE ((q.user_id IS NULL AND (q.track = ? OR q.track = 'all' OR q.track IS NULL OR q.track = '')) OR (q.user_id = ? AND (q.track = ? OR q.track IS NULL OR q.track = '')))
    ORDER BY q.week ASC, q.id ASC
");
if (!$stmt) {
    $stmt = $conn->prepare("
        SELECT q.id, q.user_id, q.week, q.title, q.description, q.xp_reward, q.is_custom, q.depends_on, uq.completed_at
        FROM quests q
        LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
        WHERE (q.user_id IS NULL OR q.user_id = ?)
        ORDER BY q.week ASC, q.id ASC
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
} else $stmt->bind_param("isis", $user_id, $myTrack, $user_id, $myTrack);
$stmt->execute();
$all_quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtasks_by_quest = [];
$st = $conn->prepare("SELECT id, user_id, quest_id, title, done_at, created_at FROM quest_subtasks WHERE user_id = ? ORDER BY id ASC");
$st->bind_param("i", $user_id);
$st->execute();
foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $s) $subtasks_by_quest[(int)$s['quest_id']][] = $s;
$st->close();

$ev_map = \App\Domain\Quest\Evidence::forQuests($conn, $user_id, array_map(fn($x) => (int)$x['id'], $all_quests));
$karya_map = \App\Domain\Dkv\Karya::forQuests($conn, $user_id, array_map(fn($x) => (int)$x['id'], $all_quests));
$rubric_sums = [];
$rubric_criteria = [];
$critique_map = [];
if ($myTrack === 'dkv') {
    $rubric_criteria = \App\Domain\Dkv\Rubric::criteria();
    $rubric_sums = \App\Domain\Dkv\Rubric::summaries($conn, $user_id, array_map(fn($x) => (int)$x['id'], $all_quests));
    \App\Domain\Dkv\Critique::ensureTables($conn);
    try {
        $ids = array_map(fn($x) => (int)$x['id'], $all_quests);
        if ($ids) {
            $in = implode(',', $ids);
            $r = $conn->query("SELECT c.quest_id, c.note, c.created_at, u.username FROM rubric_comments c JOIN users u ON u.id = c.author_id WHERE c.owner_id = " . (int)$user_id . " AND c.quest_id IN ($in) ORDER BY c.id DESC LIMIT 60");
            if ($r) { foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $critique_map[(int)$row['quest_id']][] = $row; $r->free(); }
        }
    } catch (Throwable $e) {}
}

// Compute stats
$total_quests = count($all_quests);
$completed_quests = 0;
$total_xp_possible = 0;
$xp_earned = 0;

$quests_by_week = [];
$titles_by_id = [];
$done_ids = [];
foreach ($all_quests as $q) {
    $titles_by_id[(int)$q['id']] = (string)$q['title'];
    if (!empty($q['completed_at'])) $done_ids[(int)$q['id']] = true;
}
$prev_map = quest_prev_map($all_quests);
$blocker_by_id = [];
foreach ($all_quests as $q) {
    $b = quest_blocker($q, $done_ids, $prev_map[(int)$q['id']] ?? null);
    if ($b !== null) $blocker_by_id[(int)$q['id']] = $b;
}
$next_up = quest_next_unlocked($all_quests, $done_ids, $prev_map);
$next_up_id = $next_up ? (int)$next_up['id'] : 0;
foreach ($all_quests as $q) {
    $total_xp_possible += (int)$q['xp_reward'];
    if (!empty($q['completed_at'])) {
        $completed_quests++;
        $xp_earned += (int)$q['xp_reward'];
    }
    $quests_by_week[$q['week']][] = $q;
}

$completion_rate = $total_quests > 0 ? round(($completed_quests / $total_quests) * 100) : 0;
$preselect_week = isset($_GET['week']) ? max(1, min(12, (int)$_GET['week'])) : 0;

// Tab Materi (gabungan resources.php): filter server-side via ?tab=materi&track=&mweek=
$mat_tab = ($_GET['tab'] ?? 'quest') === 'materi' ? 'materi' : 'quest';
$mat_track = $_GET['track'] ?? $myTrack;
if ($mat_track !== 'all' && !in_array($mat_track, ['devops', 'rpl', 'tkj', 'dkv'], true)) $mat_track = $myTrack;
$mat_week = max(0, min(12, (int)($_GET['mweek'] ?? 0)));
$mat_resources = []; $mat_by_week = [];
try {
    \App\Domain\Track\Roadmap::ensureSeedResources($conn);
    $msql = "SELECT id, week, title, type, url, track FROM resources WHERE 1=1";
    $mparams = []; $mtypes = '';
    if ($mat_track !== 'all') { $msql .= " AND (track = ? OR track = 'all' OR track IS NULL)"; $mparams[] = $mat_track; $mtypes .= 's'; }
    if ($mat_week > 0) { $msql .= " AND week = ?"; $mparams[] = $mat_week; $mtypes .= 'i'; }
    $msql .= " ORDER BY week ASC, type ASC, id ASC";
    if ($mparams) {
        $mst = $conn->prepare($msql);
        $mst->bind_param($mtypes, ...$mparams);
        $mst->execute();
        $mat_resources = $mst->get_result()->fetch_all(MYSQLI_ASSOC);
        $mst->close();
    } else {
        $mr = $conn->query($msql);
        $mat_resources = $mr ? $mr->fetch_all(MYSQLI_ASSOC) : [];
    }
    foreach ($mat_resources as $r) $mat_by_week[$r['week']][] = $r;
} catch (Throwable $e) {}
$mat_track_label = $mat_track === 'all' ? 'Semua Jurusan' : (\App\Domain\Track\Tracks::all()[$mat_track]['name'] ?? strtoupper($mat_track));

// Strip Hari ini (pindahan Hub): streak, peti, review jatuh tempo, progres track
$today_streak = 0; $today_freeze = 0; $today_chest_opened = false; $today_due = 0;
$track_progress = ['done' => 0, 'total' => 1, 'percent' => 0];
try {
    $hq = $conn->prepare("SELECT streak, freeze_tokens FROM users WHERE id = ?");
    if ($hq) { $hq->bind_param("i", $user_id); $hq->execute(); $urow = $hq->get_result()->fetch_assoc() ?: []; $hq->close(); $today_streak = (int)($urow['streak'] ?? 0); $today_freeze = (int)($urow['freeze_tokens'] ?? 0); }
    $hq = $conn->prepare("SELECT 1 FROM daily_chests WHERE user_id = ? AND chest_date = CURDATE()");
    if ($hq) { $hq->bind_param("i", $user_id); $hq->execute(); $today_chest_opened = (bool)$hq->get_result()->fetch_assoc(); $hq->close(); }
    $hq = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND next_due <= CURDATE()");
    if ($hq) { $hq->bind_param("i", $user_id); $hq->execute(); $today_due = (int)($hq->get_result()->fetch_assoc()['c'] ?? 0); $hq->close(); }
    $wd = \App\Domain\Track\Hub::getWidgetData($conn, $user_id, $myTrack);
    if (!empty($wd['track_progress'])) $track_progress = $wd['track_progress'];
} catch (Throwable $e) {}



$page_title = 'Quest Board - Roadmap ' . $trackName . ' 12 Minggu';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <section class="overview-header progress-strip mb-4">
        <div class="strip-main">
            <div class="page-kicker eyebrow mb-1">Roadmap <?= htmlspecialchars($trackName) ?></div>
            <h1 class="page-title mb-2">Roadmap 12 Minggu</h1>
            <p class="page-desc mb-3">Centang quest yang selesai untuk mendapatkan XP.</p>
            
            <?php if ($canSwitchTrack): ?>
            <form method="POST" action="switch_track.php" class="d-flex align-items-center gap-2 mb-3" aria-label="Ganti jurusan">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="quests.php">
                <label class="small text-muted mb-0" for="trackSel"><i class="fas fa-graduation-cap me-1"></i>Jurusan:</label>
                <select id="trackSel" name="track" class="form-select form-select-sm" style="max-width:210px" onchange="this.form.submit()">
                    <?php foreach (\App\Domain\Track\Tracks::all() as $slug => $tr): ?>
                    <option value="<?= htmlspecialchars($slug) ?>" <?= $slug === $myTrack ? 'selected' : '' ?>><?= htmlspecialchars($tr['name']) ?> (<?= htmlspecialchars($tr['desc']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-cyber-outline btn-sm" type="submit">Ganti</button></noscript>
            </form>
            <?php else: ?>
            <p class="mb-3"><span class="quest-done"><i class="fas fa-lock me-1"></i>Jurusan: <?= htmlspecialchars($trackName) ?> · Terkunci oleh kelas</span></p>
            <?php endif; ?>

            <div class="xp-progress-bar" id="roadmapBarWrap" role="progressbar" aria-valuenow="<?= $completion_rate ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres roadmap"><div class="xp-progress-fill" id="roadmapBar" style="width: <?= $completion_rate ?>%;"></div></div>
        </div>
        <div class="strip-side">
            <span><strong><span id="roadmapDone"><?= $completed_quests ?></span>/<?= $total_quests ?></strong> quest selesai</span>
            <span><strong><?= $xp_earned ?>/<?= $total_xp_possible ?></strong> XP</span>
            <span>Progres: <strong><span id="roadmapPct"><?= $completion_rate ?></span>%</strong></span>
            <?php $primaryFeature = \App\Domain\Track\Tracks::primaryFeature($myTrack); ?>
            <div class="d-flex flex-column gap-2 mt-2">
                <a class="btn btn-cyber btn-sm" href="<?= htmlspecialchars($primaryFeature['href']) ?>"><i class="<?= htmlspecialchars($primaryFeature['icon']) ?> me-1"></i><?= htmlspecialchars($primaryFeature['title']) ?></a>
                <a class="btn btn-cyber-outline btn-sm" href="lab.php?tab=mentor"><i class="fas fa-robot me-1"></i>Tanya Mentor</a>
                <a class="btn btn-cyber-outline btn-sm" href="lab.php?tab=kuis"><i class="fas fa-bolt me-1"></i>Kuis Kilat</a>
            </div>
        </div>
    </section>

    <div class="d-flex gap-2 flex-wrap align-items-center mb-3 small" aria-label="Hari ini">
        <span class="stat-chip"><i class="fas fa-fire me-1"></i><?= (int)$today_streak ?> hari<?= $today_freeze > 0 ? ' · ' . (int)$today_freeze . ' freeze' : '' ?></span>
        <span class="stat-chip">Track <?= (int)$track_progress['percent'] ?>% (<?= (int)$track_progress['done'] ?>/<?= (int)$track_progress['total'] ?>)</span>
        <?php if ($today_due > 0): ?><a href="review.php" class="text-decoration-none">Review (<?= (int)$today_due ?> antre)</a><?php endif; ?>
        <span aria-live="polite" class="d-inline-flex">
            <?php if (!$today_chest_opened): ?>
            <form method="POST" action="claim_chest.php" class="m-0" id="chestForm"><?= csrf_field() ?>
                <button class="chest-btn" type="submit" id="chestBtn"><i class="fas fa-gift me-1"></i><span>Peti +8 XP</span></button>
            </form>
            <?php else: ?><span class="stat-chip">Peti dibuka</span><?php endif; ?>
        </span>
    </div>
    <script>
    document.getElementById('chestForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('chestBtn');
        const fd = new FormData(this);
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Membuka…</span>';
        try {
            const r = await fetch('claim_chest.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const d = await r.json();
            showToast(d.message || 'Peti dibuka!', d.status === 'success' ? 'success' : 'info');
            if (d.status === 'success') btn.parentElement.innerHTML = '<span class="stat-chip">Peti dibuka</span>';
            else { btn.disabled = false; btn.innerHTML = '<i class="fas fa-gift me-1"></i><span>Peti +8 XP</span>'; }
        } catch (err) { this.submit(); }
    });
    </script>

    <div class="segmented mb-3" role="group" aria-label="Tab roadmap">
        <button type="button" class="filter-pill <?= $mat_tab === 'quest' ? 'active' : '' ?>" onclick="showRoadTab('quest', this)">Quest</button>
        <button type="button" class="filter-pill <?= $mat_tab === 'materi' ? 'active' : '' ?>" onclick="showRoadTab('materi', this)">Materi (<?= count($mat_resources) ?>)</button>
    </div>
    <p class="visually-hidden" role="status" id="questFilterCount"></p>
    <div id="questTab" <?= $mat_tab === 'materi' ? 'hidden' : '' ?>>
    <div class="mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input type="search" id="questSearch" class="form-control" placeholder="Cari quest…" aria-label="Cari quest" oninput="filterQuests()">
                </div>
            </div>
            <div class="col-md-7">
                <div class="d-flex justify-content-md-end">
                    <div class="segmented" role="group" aria-label="Filter status quest">
                        <button type="button" class="filter-pill active" onclick="filterByStatus('all', this)">Semua</button>
                        <button type="button" class="filter-pill" onclick="filterByStatus('todo', this)">Belum selesai</button>
                        <button type="button" class="filter-pill" onclick="filterByStatus('done', this)">Selesai</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 filter-pills" role="group" aria-label="Filter minggu">
            <button type="button" class="filter-pill <?= $preselect_week === 0 ? 'active' : '' ?>" onclick="filterByWeek('all', this)">Semua minggu</button>
            <?php for ($w = 1; $w <= 12; $w++): ?>
                <button type="button" class="filter-pill <?= $preselect_week === $w ? 'active' : '' ?>" onclick="filterByWeek(<?= $w ?>, this)">M-<?= $w ?></button>
            <?php endfor; ?>
        </div>
        <?php if ($preselect_week > 0): ?>
        <script>document.addEventListener('DOMContentLoaded', function() { if (typeof filterByWeek === 'function') filterByWeek(<?= $preselect_week ?>, document.querySelector('.filter-pills [onclick*="filterByWeek(<?= $preselect_week ?>"]')); });</script>
        <?php endif; ?>
    </div>

<?php
$current_active_week = 1;
foreach ($quests_by_week as $w_num => $w_quests) {
    $w_st = quest_week_stats($w_quests);
    if ($w_st['pct'] < 100) {
        $current_active_week = $w_num;
        break;
    }
}
if ($preselect_week > 0) $current_active_week = $preselect_week;
?>
    <div id="questsContainer">
        <?php foreach ($quests_by_week as $week_num => $week_quests): $wstat = quest_week_stats($week_quests); $is_active_week = $week_num === $current_active_week; ?>
            <details class="week-block week-section mission-details mb-3" data-week="<?= $week_num ?>" aria-label="Minggu <?= $week_num ?>" <?= $is_active_week ? 'open' : '' ?>>
                <summary class="mission-summary bg-body-tertiary">
                    <div class="mission-summary-text">
                        <strong>Minggu <?= $week_num ?></strong>
                        <small>Progres <?= $wstat['pct'] ?>% · <a href="quests.php?tab=materi&mweek=<?= $week_num ?>" class="text-primary text-decoration-none" onclick="event.stopPropagation()">Lihat Materi</a></small>
                    </div>
                    <span class="mission-summary-count"><?= $wstat['done'] ?>/<?= $wstat['total'] ?></span>
                    <i class="fas fa-chevron-down mission-summary-chev" aria-hidden="true"></i>
                </summary>

                <div class="mission-body pt-3">
                    <div class="week-progress mb-3" role="progressbar" aria-valuenow="<?= $wstat['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres minggu <?= $week_num ?> <?= $wstat['pct'] ?> persen"><span style="width:<?= $wstat['pct'] ?>%"></span></div>

                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($week_quests as $q):
                            $is_done = !empty($q['completed_at']);
                            $qid = (int)$q['id'];
                            $blocker = $blocker_by_id[$qid] ?? null;
                            $blocker_title = $blocker ? ($titles_by_id[$blocker] ?? 'quest sebelumnya') : '';
                            $is_next = ($qid === $next_up_id && !$is_done);
                        ?>
                            <div class="quest-item <?= $is_done ? 'completed' : ($blocker ? 'locked' : '') ?>" data-status="<?= $is_done ? 'done' : 'todo' ?>"<?= $is_next ? ' id="next"' : '' ?>>
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Interactive Checkbox Form -->
                                    <form method="POST" action="complete_quest.php" class="quest-toggle-form m-0" id="qt-<?= $qid ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                                        <?php if ($blocker): ?>
                                        <button type="submit" class="quest-check-btn" disabled title="Terkunci — selesaikan <?= htmlspecialchars($blocker_title) ?> dulu" aria-label="Quest terkunci: <?= htmlspecialchars($q['title']) ?>. Selesaikan <?= htmlspecialchars($blocker_title) ?> dulu.">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" class="quest-check-btn" title="<?= $is_done ? 'Batalkan selesai' : 'Tandai selesai (+'.$q['xp_reward'].' XP)' ?>" aria-label="<?= $is_done ? 'Batalkan quest selesai: ' : 'Tandai quest selesai: ' ?><?= htmlspecialchars($q['title']) ?>">
                                            <i class="fas <?= $is_done ? 'fa-check' : 'fa-circle' ?>"></i>
                                        </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Quest Info -->
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="quest-title-row">
                                            <h2 class="h6 fw-bold quest-title"><?= htmlspecialchars($q['title']) ?> <?php if (!empty($q['is_custom'])): ?><span class="quest-pending">Custom</span><?php endif; ?><?php if ($is_next): ?><span class="quest-pending quest-next">Berikutnya</span><?php endif; ?></h2>
                                            <span class="quest-badge-xp">
                                                <i class="fas fa-bolt" aria-hidden="true"></i> +<?= (int)$q['xp_reward'] ?> XP
                                            </span>
                                        </div>
                                        <p class="text-secondary small mb-2 quest-desc"><?= htmlspecialchars($q['description']) ?></p>
                                        <?php $subs = $subtasks_by_quest[(int)$q['id']] ?? []; $sdone = count(array_filter($subs, fn($s) => !empty($s['done_at']))); ?>
                                        <div class="d-flex align-items-center flex-wrap gap-2 quest-status-badge mb-1">
                                            <?php if ($is_done): ?>
                                                <span class="quest-done">
                                                    <i class="fas fa-check" aria-hidden="true"></i>Selesai <?= date('d M Y', strtotime($q['completed_at'])) ?>
                                                </span>
                                            <?php elseif ($blocker): ?>
                                                <span class="quest-locked">
                                                    <i class="fas fa-lock" aria-hidden="true"></i>Terkunci · selesaikan <?= htmlspecialchars(mb_strimwidth($blocker_title, 0, 45, '...')) ?> dulu
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($subs): ?><span class="small text-muted"><?= $sdone ?>/<?= count($subs) ?> langkah</span><?php endif; ?>
                                            <?php $evq = $ev_map[$qid] ?? null; if ($evq && (!empty($evq['url']) || !empty($evq['note']))): ?>
                                            <span class="small text-muted"><i class="fas fa-paperclip" aria-hidden="true"></i>
                                            <?php if (!empty($evq['url'])): ?><a href="<?= htmlspecialchars($evq['url']) ?>" target="_blank" rel="noopener">bukti</a><?php endif; ?>
                                            <?= !empty($evq['url']) && !empty($evq['note']) ? ' · ' : '' ?><?= htmlspecialchars(mb_strimwidth($evq['note'] ?? '', 0, 60, '...')) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($q['is_custom']) && (int)$q['user_id'] === $user_id): ?>
                                            <form method="POST" action="quests.php" class="m-0 ms-auto" onsubmit="return confirm('Hapus quest custom ini?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_custom">
                                                <input type="hidden" name="quest_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" class="btn btn-cyber-danger btn-sm py-1" aria-label="Hapus quest custom"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$is_done && !$blocker): ?>
                                        <details class="collapsible-card mt-2">
                                            <summary class="collapsible-summary bg-body-tertiary">
                                                <div class="collapsible-text">
                                                    <strong class="small">+ Bukti Output / Karya (opsional)</strong>
                                                </div>
                                                <i class="fas fa-chevron-down collapsible-chev" aria-hidden="true"></i>
                                            </summary>
                                            <div class="collapsible-body">
                                                <div class="d-flex flex-column gap-2 mt-2">
                                                    <input name="evidence_url" form="qt-<?= $qid ?>" class="form-control form-control-sm" placeholder="Link repo / output / catatan singkat…" maxlength="500" aria-label="Bukti quest: link atau catatan">
                                                    <?php $kw = $karya_map[$qid] ?? null; if ($kw): ?>
                                                    <a href="karya.php?id=<?= (int)$kw['id'] ?>" target="_blank" rel="noopener"><img src="karya.php?id=<?= (int)$kw['id'] ?>" alt="Karya quest" loading="lazy" style="max-width:120px;border-radius:8px"></a>
                                                    <?php endif; ?>
                                                    <details class="mt-1">
                                                        <summary class="small text-secondary" style="cursor:pointer">Punya gambar? Upload</summary>
                                                        <form method="POST" action="karya_upload.php" enctype="multipart/form-data" class="d-flex gap-2 m-0 mt-2">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="quest_id" value="<?= $qid ?>">
                                                            <input type="file" name="karya" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.gif" aria-label="Upload karya">
                                                            <button class="btn btn-cyber-outline btn-sm flex-shrink-0" type="submit">Upload</button>
                                                        </form>
                                                    </details>
                                                </div>
                                            </div>
                                        </details>
                                        <?php endif; ?>
                                        <details class="collapsible-card mt-2">
                                            <summary class="collapsible-summary bg-body-tertiary">
                                                <div class="collapsible-text">
                                                    <strong class="small">Langkah Kecil (Subtasks)</strong>
                                                    <small><?= $sdone ?>/<?= count($subs) ?> Selesai</small>
                                                </div>
                                                <i class="fas fa-chevron-down collapsible-chev" aria-hidden="true"></i>
                                            </summary>
                                            <div class="collapsible-body">
                                                <div class="subtask-list mt-2">
                                                    <?php foreach ($subs as $s): ?>
                                                    <form method="POST" action="subtask.php" class="subtask-toggle-form d-flex align-items-center gap-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="quest_id" value="<?= (int)$q['id'] ?>">
                                                        <input type="hidden" name="subtask_id" value="<?= (int)$s['id'] ?>">
                                                        <button type="submit" class="subtask-check <?= !empty($s['done_at']) ? 'done' : '' ?>" aria-label="Toggle subtask"><i class="fas <?= !empty($s['done_at']) ? 'fa-check' : 'fa-circle' ?>"></i></button>
                                                        <span class="flex-grow-1 <?= !empty($s['done_at']) ? 'text-decoration-line-through text-muted' : '' ?>"><?= htmlspecialchars($s['title']) ?></span>
                                                    </form>
                                                    <?php endforeach; ?>
                                                    <form method="POST" action="subtask.php" class="subtask-add-form d-flex gap-2 mt-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="create">
                                                        <input type="hidden" name="quest_id" value="<?= (int)$q['id'] ?>">
                                                        <input name="title" class="form-control form-control-sm" maxlength="255" placeholder="+ Tambah langkah…" aria-label="Tambah langkah">
                                                        <button type="submit" class="btn btn-cyber-outline btn-sm flex-shrink-0">Tambah</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </details>
                                        <?php if ($myTrack === 'dkv' && $is_done): $rsum = $rubric_sums[$qid] ?? null; ?>
                                        <details class="collapsible-card mt-2">
                                            <summary class="collapsible-summary bg-body-tertiary">
                                                <div class="collapsible-text">
                                                    <strong class="small">Penilaian Karya (Rubrik)</strong>
                                                    <small>Nilai: <?= $rsum !== null ? htmlspecialchars((string)$rsum) . '/5' : 'Belum dinilai' ?></small>
                                                </div>
                                                <i class="fas fa-chevron-down collapsible-chev" aria-hidden="true"></i>
                                            </summary>
                                            <div class="collapsible-body">
                                                <div class="d-flex flex-column gap-2 mt-2">
                                                <form method="POST" action="rubric.php" class="d-flex flex-column gap-2 m-0">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="quest_id" value="<?= $qid ?>">
                                                    <?php foreach ($rubric_criteria as $rc): ?>
                                                    <label class="small text-muted mb-0"><?= htmlspecialchars($rc['name']) ?> (<?= (int)$rc['weight'] ?>%)<select name="score[<?= htmlspecialchars($rc['slug']) ?>]" class="form-select form-select-sm" aria-label="<?= htmlspecialchars($rc['name']) ?>"><?php for ($sv = 1; $sv <= 5; $sv++): ?><option value="<?= $sv ?>" <?= $sv === 3 ? 'selected' : '' ?>><?= $sv ?></option><?php endfor; ?></select></label>
                                                    <?php endforeach; ?>
                                                    <input name="note" class="form-control form-control-sm" maxlength="300" placeholder="Catatan (opsional)" aria-label="Catatan rubrik">
                                                    <button class="btn btn-cyber-outline btn-sm" type="submit">Simpan nilai</button>
                                                </form>
                                                <?php foreach (($critique_map[$qid] ?? []) as $cm): ?><p class="small mb-1 mt-2"><strong><?= htmlspecialchars($cm['username']) ?>:</strong> <?= htmlspecialchars($cm['note']) ?></p><?php endforeach; ?>
                                                <form method="POST" action="critique.php" class="d-flex gap-2 m-0 mt-2"><?= csrf_field() ?><input type="hidden" name="quest_id" value="<?= $qid ?>"><input type="hidden" name="owner_id" value="<?= (int)$user_id ?>"><input name="note" class="form-control form-control-sm" maxlength="500" placeholder="Minta critique / balas…" aria-label="Critique"><button class="btn btn-cyber-outline btn-sm" type="submit">Kirim</button></form>
                                                </div>
                                            </div>
                                        </details>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <button type="button" class="fab-add" data-bs-toggle="modal" data-bs-target="#customQuestModal" aria-label="Tambah quest custom"><i class="fas fa-plus" aria-hidden="true"></i></button>

    <div class="modal fade" id="customQuestModal" tabindex="-1" aria-labelledby="customQuestLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-bottom">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h2 class="modal-title h6 fw-bold mb-0" id="customQuestLabel">Quest custom</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="quests.php">
                    <div class="modal-body">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_custom">
                        <div class="mb-3"><label class="form-label" for="cq-title">Judul</label><input id="cq-title" name="title" class="form-control" required maxlength="255" placeholder="Contoh: Latihan JOIN 30 menit"></div>
                        <div class="mb-3"><label class="form-label" for="cq-desc">Deskripsi</label><textarea id="cq-desc" name="description" class="form-control" rows="3" placeholder="Target kecil yang jelas…"></textarea></div>
                        <div class="row g-3"><div class="col-6"><label class="form-label" for="cq-week">Minggu</label><select id="cq-week" name="week" class="form-select"><?php for ($w = 1; $w <= 12; $w++): ?><option value="<?= $w ?>">Minggu <?= $w ?></option><?php endfor; ?></select></div><div class="col-6"><label class="form-label" for="cq-xp">Reward (5–20 XP)</label><input id="cq-xp" name="xp_reward" type="number" min="5" max="20" value="10" class="form-control"></div></div>
                    </div>
                    <div class="modal-footer border-top sticky-bottom-bar">
                        <button type="button" class="btn btn-cyber-outline" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-cyber">Simpan quest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Empty state for search filter -->
    <div id="noQuestsMessage" class="empty-state card p-5 d-none">
        <div class="empty-state-icon"><i class="fas fa-search-minus"></i></div>
        <h2 class="h5 fw-bold mb-2">Tidak ada quest yang cocok</h2>
        <p class="text-secondary small mb-3">Coba ubah kata kunci pencarian atau reset filter minggu.</p>
        <div>
            <button class="btn btn-cyber-outline btn-sm" onclick="resetFilters()">
                <i class="fas fa-redo me-1"></i> Reset Semua Filter
            </button>
        </div>
    </div>
    </div><!-- /questTab -->
    <div id="materiTab" <?= $mat_tab === 'quest' ? 'hidden' : '' ?>>
        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted fw-bold me-1"><i class="fas fa-layer-group me-1" aria-hidden="true"></i><?= htmlspecialchars($mat_track_label) ?>:</span>
            <?php foreach (['rpl' => 'RPL', 'tkj' => 'TKJ', 'dkv' => 'DKV', 'devops' => 'DevOps'] as $tkey => $tname): ?>
                <a href="quests.php?tab=materi&track=<?= $tkey ?><?= $mat_week ? '&mweek=' . $mat_week : '' ?>" class="btn btn-sm <?= $mat_track === $tkey ? 'btn-cyber' : 'btn-cyber-outline' ?> py-1 px-3"><?= $tname ?><?= $tkey === $myTrack ? ' <span class="badge bg-success ms-1 small">Kamu</span>' : '' ?></a>
            <?php endforeach; ?>
            <a href="quests.php?tab=materi&track=all<?= $mat_week ? '&mweek=' . $mat_week : '' ?>" class="btn btn-sm <?= $mat_track === 'all' ? 'btn-cyber' : 'btn-cyber-outline' ?> py-1 px-3">Semua</a>
        </div>
        <div class="row g-3 align-items-center mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input type="search" id="resourceSearch" class="form-control" placeholder="Cari materi…" aria-label="Cari materi" oninput="filterResources()">
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-md-end">
                    <div class="segmented" role="group" aria-label="Filter tipe materi">
                        <button type="button" class="filter-pill active" onclick="filterByType('all', this)">Semua</button>
                        <button type="button" class="filter-pill" onclick="filterByType('video', this)">Video</button>
                        <button type="button" class="filter-pill" onclick="filterByType('dokumentasi', this)">Dokumen</button>
                        <button type="button" class="filter-pill" onclick="filterByType('praktek', this)">Praktek</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3 filter-pills mb-3" role="group" aria-label="Filter minggu materi">
            <a href="quests.php?tab=materi&track=<?= urlencode($mat_track) ?>" class="filter-pill <?= $mat_week === 0 ? 'active' : '' ?>">Semua minggu</a>
            <?php for ($w = 1; $w <= 12; $w++): ?>
                <a href="quests.php?tab=materi&track=<?= urlencode($mat_track) ?>&mweek=<?= $w ?>" class="filter-pill <?= $mat_week === $w ? 'active' : '' ?>">M-<?= $w ?></a>
            <?php endfor; ?>
        </div>
        <div id="resourceContainer">
            <?php if (!empty($mat_by_week)): ?>
                <?php foreach ($mat_by_week as $w_num => $w_items): ?>
                    <section class="week-block resource-week-group" data-week="<?= $w_num ?>" aria-label="Minggu <?= $w_num ?>">
                        <div class="week-block-head">
                            <span class="week-tag">Minggu <?= $w_num ?></span>
                            <span class="week-count"><?= count($w_items) ?> materi</span>
                            <span class="rule" aria-hidden="true"></span>
                        </div>
                        <div>
                            <?php foreach ($w_items as $res): ?>
                                <div class="resource-item" data-type="<?= htmlspecialchars($res['type']) ?>">
                                    <a class="list-row" href="<?= htmlspecialchars($res['url']) ?>" target="_blank" rel="noopener noreferrer">
                                        <div class="list-main">
                                            <p class="list-title"><?= htmlspecialchars($res['title']) ?></p>
                                            <p class="list-meta"><?= htmlspecialchars(ucfirst($res['type'])) ?> · Minggu <?= (int)$res['week'] ?></p>
                                        </div>
                                        <i class="fas fa-arrow-up-right-from-square list-chev" aria-hidden="true"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card p-4 p-md-5 text-center empty-state">
                    <div class="empty-state-icon"><i class="fas fa-book-reader"></i></div>
                    <h2 class="h5 fw-bold mb-2">Tidak ada materi untuk filter ini</h2>
                    <p class="text-secondary small mb-3">Coba minggu atau jurusan lain.</p>
                    <div><a href="quests.php?tab=materi" class="btn btn-cyber-outline btn-sm">Lihat semua</a></div>
                </div>
            <?php endif; ?>
        </div>
        <div id="noResourcesSearch" class="card p-4 p-md-5 text-center empty-state d-none">
            <div class="empty-state-icon"><i class="fas fa-search-minus"></i></div>
            <h2 class="h5 fw-bold mb-2">Tidak ada materi yang cocok</h2>
            <p class="text-secondary small mb-0">Coba kata kunci yang lebih umum.</p>
        </div>
    </div>
<script>
function showRoadTab(which, btn) {
    document.getElementById('questTab').hidden = which !== 'quest';
    document.getElementById('materiTab').hidden = which !== 'materi';
    btn.parentElement.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
let activeType = 'all';
function filterByType(type, btn) {
    activeType = type;
    btn.parentElement.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyResourceFilters();
}
function filterResources() { applyResourceFilters(); }
function applyResourceFilters() {
    const input = document.getElementById('resourceSearch');
    const query = ((input && input.value) || '').toLowerCase().trim();
    const groups = document.querySelectorAll('.resource-week-group');
    let visibleGroupCount = 0;
    groups.forEach(group => {
        const items = group.querySelectorAll('.resource-item');
        let visibleItemsInGroup = 0;
        items.forEach(item => {
            const itemType = item.getAttribute('data-type');
            const title = (item.querySelector('.list-title')?.textContent || '').toLowerCase();
            if ((activeType === 'all' || activeType === itemType) && (!query || title.includes(query))) {
                item.style.display = '';
                visibleItemsInGroup++;
            } else {
                item.style.display = 'none';
            }
        });
        if (visibleItemsInGroup > 0) { group.style.display = ''; visibleGroupCount++; }
        else { group.style.display = 'none'; }
    });
    const noResult = document.getElementById('noResourcesSearch');
    if (groups.length > 0) {
        if (visibleGroupCount === 0) noResult.classList.remove('d-none');
        else noResult.classList.add('d-none');
    }
}
</script>
</main>

<script>
let activeWeek = 'all';
let activeStatus = 'all';

function filterByWeek(week, btn) {
    activeWeek = String(week);
    document.querySelectorAll('.filter-pills button').forEach(b => {
        const label = (b.innerText || '').trim().toLowerCase();
        if (label.startsWith('m-') || label === 'semua minggu') {
            b.classList.remove('active');
        }
    });
    btn.classList.add('active');
    applyFilters();
}

function filterByStatus(status, btn) {
    activeStatus = status;
    btn.parentElement.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function filterQuests() {
    applyFilters();
}

function applyFilters() {
    const query = (document.getElementById('questSearch').value || '').toLowerCase().trim();
    const sections = document.querySelectorAll('.week-section');
    let visibleSectionCount = 0;

    sections.forEach(section => {
        const weekNum = section.getAttribute('data-week');
        const matchWeek = (activeWeek === 'all' || activeWeek === weekNum);

        const items = section.querySelectorAll('.quest-item');
        let visibleItemsInSection = 0;

        items.forEach(item => {
            const status = item.getAttribute('data-status');
            const title = (item.querySelector('.quest-title')?.textContent || '').toLowerCase();
            const desc = (item.querySelector('p')?.textContent || '').toLowerCase();

            const matchStatus = (activeStatus === 'all' || activeStatus === status);
            const matchSearch = (!query || title.includes(query) || desc.includes(query));

            if (matchStatus && matchSearch) {
                item.style.display = '';
                visibleItemsInSection++;
            } else {
                item.style.display = 'none';
            }
        });

        if (matchWeek && visibleItemsInSection > 0) {
            section.style.display = '';
            if (section.tagName === 'DETAILS') section.open = true;
            visibleSectionCount++;
        } else {
            section.style.display = 'none';
        }
    });

    const noQuestsMsg = document.getElementById('noQuestsMessage');
    if (visibleSectionCount === 0) {
        noQuestsMsg.classList.remove('d-none');
    } else {
        noQuestsMsg.classList.add('d-none');
    }
    const live = document.getElementById('questFilterCount');
    if (live) live.textContent = visibleSectionCount === 0 ? 'Tidak ada quest yang cocok.' : visibleSectionCount + ' minggu ditampilkan.';
}

function resetFilters() {
    document.getElementById('questSearch').value = '';
    activeWeek = 'all';
    activeStatus = 'all';
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    document.querySelector('.filter-pill:first-child').classList.add('active');
    applyFilters();
}
</script>

<?php require_once 'includes/footer.php'; ?>
