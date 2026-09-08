<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Get user data sekali jalan (termasuk track + last_login agar navbar tak query ulang)
$stmt = $conn->prepare("SELECT id, username, email, xp, streak, last_active_date, last_login_at, freeze_tokens, best_streak, show_on_board, public_profile, flair, avatar_frame, role, track, created_at, onboarded FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$myTrack = \App\Domain\Track\Tracks::normalize((string)($user['track'] ?? 'devops'));

if (!$user) {
    session_destroy();
    redirect('login.php');
}
if (empty($user['onboarded'])) redirect('onboarding.php');

// Calculate level & gamification stats
$level = calculate_level($user['xp']);
$rank_title = get_user_rank($level);
$next_level_xp = xp_to_next_level($user['xp']);
$base_level_xp = level_base_xp($level);
$progress_percent = level_progress_percent($user['xp']);
$xp_needed = max(0, $next_level_xp - $user['xp']);

// Calculate roadmap week based on user registration
$created = new DateTime($user['created_at']);
$now = new DateTime();
$days_diff = (int)$created->diff($now)->days;
$auto_week = min(12, max(1, (int)floor($days_diff / 7) + 1));

// Allow manual week preview if selected
$selected_week = isset($_GET['week']) ? max(1, min(12, (int)$_GET['week'])) : $auto_week;

// Quests for selected week (global track aktif + milik sendiri)
$stmt = $conn->prepare("
    SELECT q.*, uq.completed_at
    FROM quests q
    LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
    WHERE q.week = ? AND ((q.user_id IS NULL AND (q.track = ? OR q.track = 'all' OR q.track IS NULL OR q.track = '')) OR (q.user_id = ? AND (q.track = ? OR q.track IS NULL OR q.track = '')))
    ORDER BY q.id ASC
");
if (!$stmt) {
    $stmt = $conn->prepare("
        SELECT q.*, uq.completed_at
        FROM quests q
        LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
        WHERE q.week = ? AND (q.user_id IS NULL OR q.user_id = ?)
        ORDER BY q.id ASC
    ");
    $stmt->bind_param("iii", $user_id, $selected_week, $user_id);
} else $stmt->bind_param("isiss", $user_id, $selected_week, $myTrack, $user_id, $myTrack);
$stmt->execute();
$quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$lock_done = [];
$lock_prev = [];
try {
    $ld = $conn->prepare("SELECT quest_id FROM user_quests WHERE user_id = ?");
    $ld->bind_param("i", $user_id);
    $ld->execute();
    foreach ($ld->get_result()->fetch_all(MYSQLI_ASSOC) as $lr) $lock_done[(int)$lr['quest_id']] = true;
    $ld->close();
    // Rantai prev global di-cache per track (relatif statis, invalidasi manual tak perlu)
    $lock_prev = \App\Cache\Store::remember('lockprev:' . $myTrack, 300, function () use ($conn, $myTrack) {
        $map = [];
        try {
            $lg = $conn->prepare("SELECT id FROM quests WHERE user_id IS NULL AND track = ? ORDER BY week ASC, id ASC");
            if (!$lg) { $lg = $conn->prepare("SELECT id FROM quests WHERE user_id IS NULL ORDER BY week ASC, id ASC"); $lg->execute(); }
            else { $lg->bind_param("s", $myTrack); $lg->execute(); }
            $pg = null;
            foreach ($lg->get_result()->fetch_all(MYSQLI_ASSOC) as $gr) {
                $gid = (int)$gr['id'];
                $map[$gid] = $pg;
                $pg = $gid;
            }
            $lg->close();
        } catch (Throwable $e) {}
        return $map;
    });
} catch (Throwable $e) {}

// Dashboard counts dalam 1 roundtrip, cache 60s (invalidasi di Ledger::award)
$dash_counts = \App\Cache\Store::remember(\App\Cache\Keys::dashboard($user_id), \App\Cache\Keys::DASHBOARD_TTL, function () use ($conn, $user_id, $myTrack) {
    $stmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM user_quests uq JOIN quests q ON q.id = uq.quest_id WHERE uq.user_id = ? AND ((q.user_id IS NULL AND (q.track = ? OR q.track = 'all' OR q.track IS NULL OR q.track = '')) OR (q.user_id = ? AND (q.track = ? OR q.track IS NULL OR q.track = '')))) AS total_done, (SELECT COUNT(*) FROM quests WHERE (user_id IS NULL AND (track = ? OR track = 'all' OR track IS NULL OR track = '')) OR (user_id = ? AND (track = ? OR track IS NULL OR track = ''))) AS total_cnt, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= CURDATE() AND completed_at < CURDATE() + INTERVAL 1 DAY) AS pomo_today, (SELECT COALESCE(SUM(amount),0) FROM xp_events WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS xp_week, (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due <= CURDATE()) AS due_reviews");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM user_quests uq JOIN quests q ON q.id = uq.quest_id WHERE uq.user_id = ? AND (q.user_id IS NULL OR q.user_id = ?)) AS total_done, (SELECT COUNT(*) FROM quests WHERE user_id IS NULL OR user_id = ?) AS total_cnt, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= CURDATE() AND completed_at < CURDATE() + INTERVAL 1 DAY) AS pomo_today, (SELECT COALESCE(SUM(amount),0) FROM xp_events WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS xp_week, (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due <= CURDATE()) AS due_reviews");
        $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    } else $stmt->bind_param("isisisiiii", $user_id, $myTrack, $user_id, $myTrack, $myTrack, $user_id, $myTrack, $user_id, $user_id, $user_id);
    $stmt->execute();
    $out = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return $out;
});
$total_completed = (int)($dash_counts['total_done'] ?? 0);
$total_quests_cnt = (int)($dash_counts['total_cnt'] ?? 14);
$pomodoro_today = (int)($dash_counts['pomo_today'] ?? 0);
$xp_week = max(0, (int)($dash_counts['xp_week'] ?? 0));
$due_reviews = (int)($dash_counts['due_reviews'] ?? 0);

// Peti harian hari ini (sudah dibuka atau belum)
$chest = null;
try {
    $stmt = $conn->prepare("SELECT xp, `freeze`, is_golden FROM daily_chests WHERE user_id = ? AND chest_date = CURDATE()");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT xp, `freeze` FROM daily_chests WHERE user_id = ? AND chest_date = CURDATE()");
    }
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $chest = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
} catch (Throwable $e) {}
$overall_quest_percent = $total_quests_cnt > 0 ? round(($total_completed / $total_quests_cnt) * 100) : 0;
// Status misi harian di-cache 45 detik (invalidasi otomatis saat XP masuk via Ledger::award)
$missions = \App\Cache\Store::remember(\App\Cache\Keys::missions($user_id, date('Y-m-d')), 45, function () use ($conn, $user_id) {
    return get_daily_mission_status($conn, $user_id);
});

// Recent errors
$stmt = $conn->prepare("SELECT id, user_id, category, error_message, solution, created_at FROM errors WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_errors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Resources for selected week (track-aware with fallback)
$resources = [];
try {
    $stmt = $conn->prepare("SELECT id, week, title, type, url FROM resources WHERE week = ? AND (track = ? OR track = 'all' OR track IS NULL) ORDER BY type ASC, id ASC");
    if ($stmt) {
        $stmt->bind_param("is", $selected_week, $myTrack);
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Throwable $e) {}
if (empty($resources)) {
    try {
        $stmt = $conn->prepare("SELECT id, week, title, type, url FROM resources WHERE week = ? ORDER BY type ASC, id ASC LIMIT 3");
        if ($stmt) {
            $stmt->bind_param("i", $selected_week);
            $stmt->execute();
            $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } catch (Throwable $e) {}
}





$page_title = 'Dashboard Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" id="main">
    <!-- Hero / Header Section -->
    <section class="overview-header progress-strip mb-4">
        <div class="strip-main">
            <div class="page-kicker eyebrow mb-1"><span data-greet>Semangat</span> · Track <?= htmlspecialchars(\App\Domain\Track\Tracks::all()[$myTrack]['name'] ?? 'DevOps') ?> · Minggu <?= $selected_week ?></div>
            <h1 class="page-title mb-2">Dashboard</h1>
            <p class="page-desc mb-3">Fokus hari ini: <strong><?= !empty($quests) ? htmlspecialchars($quests[0]['title']) : 'belum ada quest' ?></strong>.</p>
            <div class="strip-level"><strong>Level <?= $level ?></strong><span><?= htmlspecialchars($rank_title) ?></span></div>
            <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $progress_percent ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres menuju level berikutnya"><div class="xp-progress-fill" id="levelProgressBar" style="width: <?= $progress_percent ?>%;"></div></div>
            <div class="strip-meta"><span><span id="statTotalXp"><?= (int)$user['xp'] ?></span> XP</span><span id="nextLevelXpText"><?= $xp_needed ?> XP lagi</span></div>
        </div>
        <div class="strip-side">
            <span><strong><?= (int)$user['streak'] ?></strong> hari konsisten</span>
            <span><strong>+<span id="dashWeekXp"><?= (int)$xp_week ?></span> XP</strong> minggu ini</span>
            <span><strong><span id="dashQuestDone"><?= $total_completed ?></span>/<?= $total_quests_cnt ?></strong> quest (<span id="dashQuestPct"><?= $overall_quest_percent ?></span>%)</span>
            <div class="d-flex gap-2 mt-2 justify-content-end">
                <a href="timer.php" class="btn btn-cyber btn-sm"><i class="fas fa-play me-1" aria-hidden="true"></i> Fokus</a>
                <button type="button" class="btn btn-cyber-outline btn-sm" id="dashShareBtn" data-username="<?= htmlspecialchars($user['username']) ?>" data-level="<?= $level ?>" data-rank="<?= htmlspecialchars($rank_title) ?>" data-streak="<?= (int)$user['streak'] ?>" data-xp="<?= (int)$user['xp'] ?>" data-quests="<?= $total_completed ?>/<?= $total_quests_cnt ?>"><i class="fas fa-share-nodes me-1" aria-hidden="true"></i> Bagikan</button>
            </div>
        </div>
    </section>
    
    <script>
    document.getElementById('dashShareBtn')?.addEventListener('click', function() {
        const d = this.dataset;
        const canvas = drawProgressCard({ username: d.username, level: d.level, rank: d.rank, streak: d.streak, xp: d.xp, quests: d.quests });
        shareCanvasImage(canvas, 'progres-' + d.username + '.png', 'Progres belajarku', d.username + ' — Level ' + d.level + ' ' + d.rank + ', ' + d.streak + ' hari streak di Learn Tracker!');
    });
    </script>

<?php
// Alerts logic (Will be displayed in sidebar)
$has_alerts = $chest || $streak_risk || in_array($next_action['type'], ['claim', 'recovery'], true);
?>


    <div class="ticker" id="communityTicker" aria-label="Aktivitas komunitas terbaru" hidden>
        <div class="ticker-track" id="tickerTrack" aria-hidden="false"></div>
        <button type="button" class="ticker-pause" id="tickerPause" aria-pressed="false" aria-label="Jeda animasi aktivitas komunitas"><i class="fas fa-pause" aria-hidden="true"></i></button>
    </div>
    <script>
    (function() {
        var box = document.getElementById('communityTicker');
        var track = document.getElementById('tickerTrack');
        if (!box || !track) return;
        function show(items) {
            if (!items || items.length < 2) return;
            var html = '';
            items.forEach(function(it) {
                var tmp = document.createElement('div');
                tmp.textContent = (it.user || '?') + ' +' + (it.amount || 0) + ' ' + (it.label || 'XP');
                html += '<span>' + tmp.innerHTML + '</span>';
            });
            track.innerHTML = html + html;
            box.hidden = false;
        }
        if ('requestIdleCallback' in window) requestIdleCallback(function() {
            fetch('public/api/v1/activity.php', { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); }).then(function(d) { show(d.items); }).catch(function() {});
        });
        else setTimeout(function() {
            fetch('public/api/v1/activity.php', { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); }).then(function(d) { show(d.items); }).catch(function() {});
        }, 2500);
    })();
    </script>

    <!-- Main Content Area -->
    <div class="row g-4">
        <!-- Left Column: Quest Board for Selected Week -->
        <div class="col-lg-8">
            <section aria-labelledby="week-target-heading">
                <div class="quest-section-head">
                    <div>
                        <h2 id="week-target-heading">Target minggu ini</h2>
                        <p>Pilih satu target berikutnya.</p>
                    </div>

                    <!-- Week selector quick dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-cyber-outline btn-sm dropdown-toggle py-1 px-3" type="button" data-bs-toggle="dropdown" aria-label="Pilih minggu roadmap, saat ini minggu <?= $selected_week ?>">
                            Minggu <?= $selected_week ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end p-2" style="max-height: 280px; overflow-y: auto;">
                            <?php for ($w = 1; $w <= 12; $w++): ?>
                                <li>
                                    <a class="dropdown-item rounded py-1 px-3 small <?= $w === $selected_week ? 'active' : '' ?>" href="index.php?week=<?= $w ?>">
                                        Minggu <?= $w ?> <?= $w === $auto_week ? ' (Minggu Kamu)' : '' ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                </div>

                <!-- Quest items list -->
                <div class="quest-list d-flex flex-column gap-2">
                    <?php if (!empty($quests)): ?>
                        <?php $ev_map = \App\Domain\Quest\Evidence::forQuests($conn, $user_id, array_map(fn($x) => (int)$x['id'], $quests)); ?>
                        <?php foreach ($quests as $q):
                            $is_done = !empty($q['completed_at']);
                            $ev = $ev_map[(int)$q['id']] ?? null;
                        ?>
                            <div class="quest-item <?= $is_done ? 'completed' : '' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Interactive Checkbox Form -->
                                    <form method="POST" action="complete_quest.php" class="quest-toggle-form m-0" id="qt-<?= (int)$q['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="quest-check-btn" title="<?= $is_done ? 'Batalkan selesai' : 'Tandai selesai (+'.$q['xp_reward'].' XP)' ?>" aria-label="<?= $is_done ? 'Batalkan quest selesai: ' : 'Tandai quest selesai: ' ?><?= htmlspecialchars($q['title']) ?>">
                                            <i class="fas <?= $is_done ? 'fa-check' : 'fa-circle' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Quest Info -->
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="quest-title-row">
                                            <h3 class="h6 fw-bold quest-title"><?= htmlspecialchars($q['title']) ?></h3>
                                            <span class="quest-badge-xp">
                                                <i class="fas fa-bolt" aria-hidden="true"></i> +<?= (int)$q['xp_reward'] ?> XP
                                            </span>
                                        </div>
                                        <p class="text-secondary small mb-2"><?= htmlspecialchars($q['description']) ?></p>
                                        <div class="d-flex align-items-center gap-2 quest-status-badge">
                                            <?php if ($is_done): ?>
                                                <span class="quest-done">
                                                    <i class="fas fa-check" aria-hidden="true"></i>Selesai <?= date('d M Y', strtotime($q['completed_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($ev && (!empty($ev['url']) || !empty($ev['note']))): ?>
                                                <span class="small text-muted"><i class="fas fa-paperclip" aria-hidden="true"></i>
                                                <?php if (!empty($ev['url'])): ?><a href="<?= htmlspecialchars($ev['url']) ?>" target="_blank" rel="noopener">bukti</a><?php endif; ?>
                                                <?= !empty($ev['url']) && !empty($ev['note']) ? ' · ' : '' ?><?= htmlspecialchars(mb_strimwidth($ev['note'] ?? '', 0, 60, '...')) ?></span>
                                            <?php endif; ?>
                                            <?php if (!$is_done): ?>
                                            <details class="small mt-1">
                                                <summary class="text-muted" style="cursor:pointer">+ bukti (opsional)</summary>
                                                <div class="d-flex flex-column gap-1 mt-1">
                                                    <input name="evidence_url" form="qt-<?= (int)$q['id'] ?>" class="form-control form-control-sm" placeholder="Link repo / output…" maxlength="500" inputmode="url" aria-label="Link bukti quest">
                                                    <input name="evidence_note" form="qt-<?= (int)$q['id'] ?>" class="form-control form-control-sm" placeholder="Catatan singkat…" maxlength="500" aria-label="Catatan bukti quest">
                                                </div>
                                            </details>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></div>
                            <h3 class="h6 text-secondary">Belum ada quest di minggu <?= $selected_week ?>.</h3>
                            <p class="small text-muted">Kembali ke minggu berjalan atau buka roadmap penuh.</p>
                            <div class="d-flex gap-2 justify-content-center flex-wrap mt-2">
                                <a href="index.php?week=<?= $auto_week ?>" class="btn btn-cyber btn-sm">Ke minggu <?= $auto_week ?></a>
                                <a href="quests.php" class="btn btn-cyber-outline btn-sm">Buka roadmap</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-3">
                    <a href="quests.php" class="btn btn-cyber-outline w-100">Buka seluruh roadmap</a>
                </div>
            </section>
        </div>

        <!-- Right Column: Sidebar -->
        <div class="col-lg-4 d-flex flex-column gap-4">
            
            <?php if ($has_alerts): ?>
            <section aria-labelledby="alerts-heading" class="d-flex flex-column gap-2 mb-2">
                <h2 id="alerts-heading" class="visually-hidden">Pemberitahuan</h2>
                
                <?php if ($streak_risk): ?>
                <a class="streak-banner" href="timer.php">
                    <i class="fas fa-fire" aria-hidden="true"></i>
                    <span><strong>Streak <?= (int)$user['streak'] ?> hari belum aman.</strong> Satu aksi kecil sebelum tengah malam<?php if ((int)($user['freeze_tokens'] ?? 0) > 0): ?> · freeze tersisa <?= (int)$user['freeze_tokens'] ?><?php endif; ?>.</span>
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
                <?php endif; ?>

                <?php if ($next_action['type'] === 'claim'): ?>
                <div class="next-action next-action-primary" data-urgent="<?= $next_urgent ?>">
                    <span class="next-action-icon" aria-hidden="true"><i class="fas fa-gift"></i></span>
                    <span class="next-action-text"><strong><?= htmlspecialchars($next_action['title']) ?></strong><small><?= htmlspecialchars($next_action['desc']) ?></small></span>
                    <form method="POST" action="claim_mission.php" class="m-0 flex-shrink-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="mission_key" value="all">
                        <button type="submit" class="btn btn-cyber btn-sm">Klaim</button>
                    </form>
                </div>
                <?php elseif ($next_action['type'] === 'recovery'): ?>
                <a class="next-action next-action-primary" href="<?= htmlspecialchars($next_action['href']) ?>" data-urgent="<?= $next_urgent ?>">
                    <span class="next-action-icon" aria-hidden="true"><i class="fas <?= $next_icons[$next_action['type']] ?? 'fa-arrow-right' ?>"></i></span>
                    <span class="next-action-text"><strong><?= htmlspecialchars($next_action['title']) ?></strong><small><?= htmlspecialchars($next_action['desc']) ?></small></span>
                    <i class="fas fa-chevron-right list-chev" aria-hidden="true"></i>
                </a>
                <?php endif; ?>

                <div class="chest-card<?= $chest ? ' opened' : '' ?>" id="dailyChest" data-urgent="<?= $chest ? '0' : '1' ?>">
                    <?php if ($chest): ?>
                        <span class="chest-icon" aria-hidden="true"><i class="fas fa-gift"></i></span>
                        <span class="chest-text"><strong><?= !empty($chest['is_golden']) ? 'Peti emas! ' : '' ?>+<?= (int)$chest['xp'] ?> XP<?= !empty($chest['freeze']) ? ' + 1 pelindung streak' : '' ?></strong><small>peti hari ini sudah dibuka · kembali besok</small></span>
                    <?php else: ?>
                        <span class="chest-icon closed" aria-hidden="true"><i class="fas fa-gift"></i></span>
                        <span class="chest-text"><strong>Peti harian menunggumu</strong><small>Hadiah 3–15 XP + pelindung streak sesekali</small></span>
                        <form method="POST" action="claim_chest.php" class="chest-form m-0 flex-shrink-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-cyber btn-sm">Buka</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php
            $mission_claimed = count(array_filter($missions, fn($m) => !empty($m['claimed'])));
            $mission_all_done = count(array_filter($missions, fn($m) => !empty($m['done']))) === count($missions);
            ?>
            <div class="bolt-say mb-2" data-bolt data-context="<?= htmlspecialchars($bolt_ctx) ?>" data-mood="<?= htmlspecialchars($bolt_mood) ?>" data-msg="<?= $bolt_msg ?>"></div>
            
            <section class="mission-strip" aria-label="Misi harian">
                <details class="mission-details" id="missionDetails" open>
                    <summary class="mission-summary">
                        <span class="mission-summary-text">
                            <strong>Misi hari ini</strong>
                            <?php $combo_done = \App\Domain\Gamification\Combo::countDone($missions); $combo_total = count($missions) ?: 3; $combo_mult = \App\Domain\Gamification\Combo::tier($combo_done, $combo_total); ?>
                            <small><?= $mission_claimed ?>/<?= count($missions) ?> diklaim · bonus harian · <span class="text-success fw-bold">Kombo <?= \App\Domain\Gamification\Combo::label($combo_mult) ?></span> · <?= \App\Domain\Gamification\Combo::nextHint($combo_done, $combo_total) ?> · reset <span id="resetClock">--:--:--</span></small>
                        </span>
        <script>
        (function() {
            const el = document.getElementById('resetClock');
            if (!el) return;
            const pad = function(n) { return String(n).padStart(2, '0'); };
            const tick = function() {
                const now = new Date();
                const mid = new Date(now);
                mid.setHours(24, 0, 0, 0);
                let s = Math.max(0, Math.floor((mid - now) / 1000));
                el.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
            };
            tick();
            setInterval(tick, 1000);
        })();
        </script>
                        <span class="mission-summary-count" aria-hidden="true"><?= $mission_claimed ?>/<?= count($missions) ?></span>
                        <i class="fas fa-chevron-down mission-summary-chev" aria-hidden="true"></i>
                    </summary>
                    <div class="mission-body">
                        <?php if ($due_reviews > 0): ?>
                        <a class="review-banner" href="review.php"><i class="fas fa-rotate-right" aria-hidden="true"></i><span><strong><?= $due_reviews ?> review</strong> jatuh tempo hari ini — 2 menit saja.</span><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <div class="mission-row">
                            <?php foreach ($missions as $mkey => $m): ?>
                            <div class="mission-card <?= !empty($m['claimed']) ? 'claimed' : (!empty($m['done']) ? 'ready' : '') ?>" data-urgent="<?= (!empty($m['done']) && empty($m['claimed'])) ? '1' : '0' ?>">
                                <i class="fas <?= htmlspecialchars($m['icon']) ?>" aria-hidden="true"></i>
                                <div class="mission-main"><strong><?= htmlspecialchars($m['label']) ?></strong><span>+<?= (int)$m['xp'] ?> XP</span></div>
                                <?php if (!empty($m['claimed'])): ?>
                                    <span class="quest-done"><i class="fas fa-check" aria-hidden="true"></i>Diklaim</span>
                                <?php elseif (!empty($m['done'])): ?>
                                    <form method="POST" action="claim_mission.php" class="mission-claim-form m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="mission_key" value="<?= htmlspecialchars($mkey) ?>">
                                        <button type="submit" class="btn btn-cyber btn-sm">Klaim</button>
                                    </form>
                                <?php else: ?>
                                    <span class="small text-muted">Belum</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
                <script>
                (function(){var d=document.getElementById('missionDetails');if(d&&matchMedia('(max-width:767.98px)').matches){d.removeAttribute('open');}})();
                </script>
            </section>

            <section aria-labelledby="continue-material-heading">
                <div class="quest-section-head">
                    <div>
                        <h2 id="continue-material-heading">Materi minggu ini</h2>
                        <p>Referensi pendukung quest.</p>
                    </div>
                    <a href="resources.php?week=<?= $selected_week ?>" class="small text-secondary text-decoration-none">Lihat semua <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i></a>
                </div>

                <?php if (!empty($resources)): ?>
                    <div>
                        <?php foreach (array_slice($resources, 0, 3) as $res): ?>
                            <a class="list-row" href="<?= htmlspecialchars($res['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars($res['title']) ?>">
                                <div class="list-main">
                                    <p class="list-title"><?= htmlspecialchars($res['title']) ?></p>
                                    <p class="list-meta"><?= htmlspecialchars(ucfirst($res['type'])) ?> · Minggu <?= (int)$res['week'] ?></p>
                                </div>
                                <i class="fas fa-arrow-up-right-from-square list-chev" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-3">
                        <div class="empty-state-icon" aria-hidden="true"><i class="fas fa-book-open"></i></div>
                        <p class="text-secondary small mb-2">Belum ada materi untuk minggu <?= $selected_week ?>.</p>
                        <a href="resources.php?week=<?= $selected_week ?>" class="btn btn-cyber-outline btn-sm">Cari materi lain</a>
                    </div>
                <?php endif; ?>
            </section>

            <section aria-labelledby="continue-notes-heading">
                <div class="quest-section-head">
                    <div>
                        <h2 id="continue-notes-heading">Catatan terbaru</h2>
                        <p>Error dan solusi yang tersimpan.</p>
                    </div>
                    <a href="errors.php" class="small text-secondary text-decoration-none">Lihat semua <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i></a>
                </div>

                <?php if (!empty($recent_errors)): ?>
                    <div>
                        <?php foreach ($recent_errors as $err): ?>
                            <a class="list-row" href="errors.php" title="<?= htmlspecialchars($err['error_message']) ?>">
                                <div class="list-main">
                                    <p class="list-title"><?= htmlspecialchars(mb_strimwidth($err['error_message'], 0, 75, '...')) ?></p>
                                    <p class="list-meta"><?= htmlspecialchars($err['category'] ?? 'General') ?> · <?= !empty($err['solution']) ? 'Ada solusi' : 'Belum ada solusi' ?> · <?= date('d M', strtotime($err['created_at'])) ?></p>
                                </div>
                                <i class="fas fa-chevron-right list-chev" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-note-sticky" aria-hidden="true"></i></div>
                        <p class="text-secondary small mb-0">Belum ada catatan. Temui error saat coding? Catat untuk +5 XP.</p>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <a href="errors.php" class="btn btn-cyber-outline btn-sm w-100">Tulis catatan baru (+5 XP)</a>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
(function() {
    const track = document.getElementById('tickerTrack');
    const pauseBtn = document.getElementById('tickerPause');
    if (track && track.children.length > 1) track.innerHTML += track.innerHTML;
    if (pauseBtn && track) pauseBtn.addEventListener('click', function() {
        const paused = track.style.animationPlayState === 'paused';
        track.style.animationPlayState = paused ? '' : 'paused';
        this.setAttribute('aria-pressed', paused ? 'false' : 'true');
        this.setAttribute('aria-label', paused ? 'Jeda animasi aktivitas komunitas' : 'Putar animasi aktivitas komunitas');
        this.innerHTML = paused ? '<i class="fas fa-pause" aria-hidden="true"></i>' : '<i class="fas fa-play" aria-hidden="true"></i>';
    });
    let tickerVisible = true;
    if (track && 'IntersectionObserver' in window) {
        tickerVisible = false;
        new IntersectionObserver(function(entries) {
            tickerVisible = entries.some(function(en) { return en.isIntersecting; });
        }, { rootMargin: '200px' }).observe(track.closest('.ticker'));
    }
    let activityFails = 0;
    setInterval(async function() {
        if (document.hidden || !track || !tickerVisible) return;
        if (activityFails >= 3) return;
        try {
            const ctl = new AbortController();
            const to = setTimeout(function() { ctl.abort(); }, 8000);
            const res = await fetch('public/api/v1/activity.php', { headers: { 'Accept': 'application/json' }, signal: ctl.signal });
            clearTimeout(to);
            const data = await res.json();
            if (data && data.status === 'success' && Array.isArray(data.items) && data.items.length > 1) {
                let html = '';
                data.items.forEach(function(it) {
                    const tmp = document.createElement('div');
                    tmp.textContent = (it.user || '?') + ' +' + (it.amount || 0) + ' ' + (it.label || 'XP');
                    html += '<span>' + tmp.innerHTML + '</span>';
                });
                track.innerHTML = html + html;
                activityFails = 0;
            }
        } catch (err) { activityFails++; }
    }, 300000);
    let pulseFails = 0;
    setInterval(async function() {
        if (document.hidden) return;
        if (pulseFails >= 3) return;
        try {
            const ctl = new AbortController();
            const to = setTimeout(function() { ctl.abort(); }, 8000);
            const res = await fetch('public/api/v1/pulse.php', { headers: { 'Accept': 'application/json' }, signal: ctl.signal });
            clearTimeout(to);
            const data = await res.json();
            if (!data || data.status !== 'success') return;
            pulseFails = 0;
            const xpEl = document.getElementById('statTotalXp');
            if (xpEl) {
                const old = parseInt(xpEl.textContent, 10) || 0;
                if (data.xp > old) {
                    xpEl.textContent = data.xp;
                    try { xpJuice(data.xp - old, xpEl, { buzz: 12 }); } catch (err) {}
                }
            }
            const wk = document.getElementById('dashWeekXp');
            if (wk) wk.textContent = data.xp_week;
            const hs = document.getElementById('hudStreak');
            if (hs) hs.textContent = data.streak;
        } catch (err) { pulseFails++; }
    }, 120000);
})();
</script>
<script>
document.querySelector('.chest-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const card = document.getElementById('dailyChest');
    const btn = this.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; }
    card.classList.add('opening');
    let data = null;
    try {
        const res = await fetch('claim_chest.php', {
            method: 'POST',
            body: new FormData(this),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        data = await res.json();
    } catch (err) { this.submit(); return; }
    if (!data || data.status !== 'success') {
        card.classList.remove('opening');
        if (btn) btn.disabled = false;
        showToast((data && data.message) || 'Gagal membuka peti.', 'warning');
        return;
    }
    const tier = data.tier || 'common';
    const tierLabel = { common: 'Biasa', rare: 'Langka', epic: 'Epik', legendary: 'Legendaris', golden: 'Emas' }[tier] || 'Biasa';
    const tierStyle = tier === 'golden' ? 'legendary' : tier;
    const reduceMotion = window.LTMotion ? window.LTMotion.reduced() : matchMedia('(prefers-reduced-motion: reduce)').matches;
    const reveal = function() {
        card.classList.remove('opening');
        card.classList.add('opened');
        card.innerHTML = '<span class="chest-icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>'
            + '<span class="chest-text"><span class="chest-tier tier-' + tierStyle + '">' + tierLabel + '</span>'
            + '<span class="chest-reward tier-' + tierStyle + '">+' + data.xp + ' XP' + (data.freeze > 0 ? ' + 1 freeze' : '') + '</span>'
            + '<small>peti hari ini sudah dibuka · kembali besok</small></span>';
        const xpEl = document.getElementById('statTotalXp');
        if (xpEl) xpEl.textContent = (parseInt(xpEl.textContent, 10) || 0) + (parseInt(data.xp, 10) || 0);
        xpJuice(data.xp, card, { tier: tierStyle, buzz: tier === 'common' ? 12 : [20, 50, 30] });
        showToast(tierLabel + '! ' + data.message, 'success');
        try { tierHaptic(tier === 'golden' ? 'legendary' : tier); } catch (err) {}
        const chestSound = { common: 'chestCommon', rare: 'chestRare', epic: 'chestEpic', legendary: 'chestLegendary', golden: 'chestLegendary' }[tier] || 'chestCommon';
        try { if (window.SoundEffects && SoundEffects[chestSound]) SoundEffects[chestSound](); } catch (err) {}
        if (tier === 'legendary' || tier === 'epic' || tier === 'golden') {
            try { triggerConfetti(true); } catch (err) {}
        }
    };
    if (reduceMotion) { reveal(); return; }
    const cyc = card.querySelector('.chest-text');
    if (cyc) cyc.innerHTML = '<span class="chest-cycle">+?</span><small>mengocok…</small>';
    let ticks = 0;
    const slot = setInterval(function() {
        ticks++;
        const el = card.querySelector('.chest-cycle');
        if (el) el.textContent = '+' + (3 + Math.floor(Math.random() * 13)) + ' XP';
        if (ticks >= 10) { clearInterval(slot); reveal(); }
    }, 90);
});
</script>

<?php require_once 'includes/footer.php'; ?>
