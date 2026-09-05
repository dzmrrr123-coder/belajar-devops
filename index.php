<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

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

// Quests for selected week
$stmt = $conn->prepare("
    SELECT q.*, uq.completed_at
    FROM quests q
    LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
    WHERE q.week = ?
    ORDER BY q.id ASC
");
$stmt->bind_param("ii", $user_id, $selected_week);
$stmt->execute();
$quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total completed quests count
$stmt = $conn->prepare("SELECT COUNT(*) as total_done FROM user_quests WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_done_res = $stmt->get_result()->fetch_assoc();
$total_completed = (int)($total_done_res['total_done'] ?? 0);
$stmt->close();

// Total quests available
$total_quests_cnt = (int)($conn->query("SELECT COUNT(*) as cnt FROM quests")->fetch_assoc()['cnt'] ?? 14);
$overall_quest_percent = $total_quests_cnt > 0 ? round(($total_completed / $total_quests_cnt) * 100) : 0;

// Recent errors
$stmt = $conn->prepare("SELECT * FROM errors WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_errors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Resources for selected week
$stmt = $conn->prepare("SELECT * FROM resources WHERE week = ? ORDER BY type ASC, id ASC");
$stmt->bind_param("i", $selected_week);
$stmt->execute();
$resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Today pomodoro count
$stmt = $conn->prepare("SELECT COUNT(*) as pomodoro_today FROM pomodoro_sessions WHERE user_id = ? AND DATE(completed_at) = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pomodoro_today = (int)($stmt->get_result()->fetch_assoc()['pomodoro_today'] ?? 0);
$stmt->close();

$conn->close();

$page_title = 'Dashboard Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <!-- Overview header and next action -->
    <div class="overview-hero card p-4 p-md-5 mb-4 position-relative overflow-hidden">
        <div class="overview-kicker">Learning overview <span class="overview-dot" aria-hidden="true"></span> Minggu <?= $selected_week ?> dari 12</div>
        <div class="row align-items-center">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="overview-status"><span class="status-dot" aria-hidden="true"></span> Fokus belajar aktif</span>
                    <span class="overview-streak">Konsisten <?= (int)$user['streak'] ?> hari</span>
                </div>
                <h1 class="display-6 fw-bold mb-2">
                    Selamat Belajar, <span class="text-gradient"><?= htmlspecialchars($user['username']) ?></span>!
                </h1>
                <p class="text-secondary mb-3 mb-md-0" style="max-width: 600px;">
                    Lanjutkan dari langkah terakhir dan selesaikan satu target kecil hari ini. Progress belajar kamu tetap tersimpan rapi di satu tempat.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                    <a href="timer.php" class="btn btn-cyber">
                        <i class="fas fa-play me-1"></i> Mulai sesi fokus
                    </a>
                    <a href="quests.php" class="btn btn-cyber-outline">
                        <i class="fas fa-arrow-right me-1"></i> Lihat roadmap
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress summary -->
    <div class="row g-3 mb-4">
        <!-- Level & Rank -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label">Level belajar</div>
                        <div class="stat-val" id="statLevel"><?= $level ?></div>
                    </div>
                    <div class="stat-icon emerald">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>
                <div class="small fw-semibold text-emerald d-flex align-items-center gap-1" id="statRank">
                    <i class="fas fa-award"></i> <?= htmlspecialchars($rank_title) ?>
                </div>
            </div>
        </div>

        <!-- Total XP & Progress -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label">Total XP</div>
                        <div class="stat-val"><span id="statTotalXp"><?= (int)$user['xp'] ?></span> <span class="fs-6 text-gold">XP</span></div>
                    </div>
                    <div class="stat-icon gold">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between text-secondary small mb-1" style="font-size: 0.78rem;">
                    <span>Progress Level <?= $level ?></span>
                    <span id="nextLevelXpText"><?= $xp_needed ?> XP lagi</span>
                </div>
                <div class="xp-progress-bar">
                    <div class="xp-progress-fill" id="levelProgressBar" style="width: <?= $progress_percent ?>%;"></div>
                </div>
            </div>
        </div>

        <!-- Daily Streak -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label">Konsistensi</div>
                        <div class="stat-val"><?= (int)$user['streak'] ?> <span class="fs-6 text-warning">Hari</span></div>
                    </div>
                    <div class="stat-icon gold">
                        <span class="flame-icon fs-3">🔥</span>
                    </div>
                </div>
                <div class="small text-secondary">
                    <?= (int)$user['streak'] > 0 ? 'Konsistensi aktif! Pertahankan apimu.' : 'Login & selesaikan quest untuk mulai!' ?>
                </div>
            </div>
        </div>

        <!-- Roadmap & Progress -->
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label">Roadmap aktif</div>
                        <div class="stat-val">W-<?= $selected_week ?> <span class="fs-6 text-secondary">/ 12</span></div>
                    </div>
                    <div class="stat-icon cyan">
                        <i class="fas fa-map-signs"></i>
                    </div>
                </div>
                <div class="small text-cyan fw-semibold">
                    <i class="fas fa-check-double me-1"></i> <?= $total_completed ?> / <?= $total_quests_cnt ?> Quest Selesai (<?= $overall_quest_percent ?>%)
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="row g-4">
        <!-- Left Column: Quest Board for Selected Week -->
        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-3 border-bottom" style="border-color: var(--border-subtle) !important;">
                    <div>
                        <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-list-check text-primary"></i> Target minggu ini
                        </h2>
                        <small class="text-secondary">Pilih satu target berikutnya untuk menjaga momentum belajar.</small>
                    </div>

                    <!-- Week selector quick dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-cyber-outline btn-sm dropdown-toggle py-1 px-3" type="button" data-bs-toggle="dropdown">
                            Minggu <?= $selected_week ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end p-2" style="background: var(--bg-surface-elevated); max-height: 280px; overflow-y: auto;">
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
                <div class="quest-list d-flex flex-column gap-3">
                    <?php if (!empty($quests)): ?>
                        <?php foreach ($quests as $q): 
                            $is_done = !empty($q['completed_at']);
                        ?>
                            <div class="quest-item <?= $is_done ? 'completed' : '' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Interactive Checkbox Form -->
                                    <form method="POST" action="complete_quest.php" class="quest-toggle-form m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="quest-check-btn" title="<?= $is_done ? 'Batalkan selesai' : 'Tandai selesai (+'.$q['xp_reward'].' XP)' ?>">
                                            <i class="fas <?= $is_done ? 'fa-check' : 'fa-circle' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Quest Info -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                            <h3 class="h6 fw-bold mb-0 quest-title"><?= htmlspecialchars($q['title']) ?></h3>
                                            <span class="quest-badge-xp">
                                                <i class="fas fa-bolt"></i> +<?= (int)$q['xp_reward'] ?> XP
                                            </span>
                                        </div>
                                        <p class="text-secondary small mb-2"><?= htmlspecialchars($q['description']) ?></p>
                                        <div class="d-flex align-items-center gap-2 quest-status-badge">
                                            <?php if ($is_done): ?>
                                                <span class="badge bg-success" style="font-size: 0.72rem;">
                                                    <i class="fas fa-check me-1"></i>Selesai <?= date('d M Y', strtotime($q['completed_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                            <h3 class="h6 text-secondary">Tidak ada quest untuk minggu ini.</h3>
                            <p class="small text-muted">Silakan pilih minggu lainnya melalui tombol di atas.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 pt-3 border-top" style="border-color: var(--border-subtle) !important;">
                    <a href="quests.php" class="btn btn-cyber-outline w-100">
                        <i class="fas fa-th-list me-2"></i> Buka Seluruh Roadmap 12 Minggu
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column: Resources & Recent Errors -->
        <div class="col-lg-5 d-flex flex-column gap-4">
            <!-- Learning Resources for Selected Week -->
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color: var(--border-subtle) !important;">
                    <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-book-open text-cyan"></i> Materi pendukung
                    </h2>
                    <a href="resources.php?week=<?= $selected_week ?>" class="small text-secondary text-decoration-none">
                        Lihat Semua <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </div>

                <?php if (!empty($resources)): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (array_slice($resources, 0, 3) as $res): 
                            $badge_class = 'badge-dokumentasi';
                            $type_icon = 'fas fa-book';
                            if ($res['type'] === 'video') {
                                $badge_class = 'badge-video';
                                $type_icon = 'fab fa-youtube';
                            } elseif ($res['type'] === 'praktek') {
                                $badge_class = 'badge-praktek';
                                $type_icon = 'fas fa-laptop-code';
                            }
                        ?>
                            <a href="<?= htmlspecialchars($res['url']) ?>" target="_blank" rel="noopener noreferrer" class="p-3 rounded text-decoration-none resource-card">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                    <h3 class="h6 fw-semibold mb-0 text-white"><?= htmlspecialchars($res['title']) ?></h3>
                                    <span class="resource-type-badge <?= $badge_class ?>">
                                        <i class="<?= $type_icon ?>"></i> <?= ucfirst($res['type']) ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mt-2 pt-1 text-secondary small" style="font-size: 0.78rem;">
                                    <span><i class="fas fa-external-link-alt me-1"></i> Pelajari materi</span>
                                    <span class="text-muted">Buka tab baru</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary small mb-0">Belum ada sumber belajar untuk minggu ini.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Error Log Snippet -->
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color: var(--border-subtle) !important;">
                    <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-note-sticky text-rose"></i> Catatan terbaru
                    </h2>
                    <a href="errors.php" class="small text-secondary text-decoration-none">
                        Lihat Semua <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </div>

                <?php if (!empty($recent_errors)): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($recent_errors as $err): ?>
                            <div class="p-3 rounded" style="background: rgba(30, 41, 59, 0.5); border: 1px solid var(--border-subtle); border-left: 3px solid var(--accent-rose);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-dark border border-secondary text-secondary" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($err['category'] ?? 'General') ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?= date('d M', strtotime($err['created_at'])) ?>
                                    </small>
                                </div>
                                <div class="small fw-semibold text-white mb-1" style="word-break: break-all;">
                                    <?= htmlspecialchars(mb_strimwidth($err['error_message'], 0, 75, '...')) ?>
                                </div>
                                <?php if (!empty($err['solution'])): ?>
                                    <div class="small text-emerald" style="font-size: 0.8rem;">
                                        <i class="fas fa-check me-1"></i> <?= htmlspecialchars(mb_strimwidth($err['solution'], 0, 70, '...')) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="small text-warning" style="font-size: 0.78rem;">
                                        <i class="fas fa-hourglass-half me-1"></i> Belum ada solusi
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-shield-virus text-emerald fs-3 mb-2"></i>
                        <p class="text-secondary small mb-1">Belum ada catatan error!</p>
                        <p class="text-muted small mb-0">Hadapi error saat coding? Catat di Error Log untuk dapat +5 XP.</p>
                    </div>
                <?php endif; ?>

                <div class="mt-3 pt-2">
                    <a href="errors.php" class="btn btn-cyber-outline btn-sm w-100">
                        <i class="fas fa-plus-circle me-1"></i> Tulis Error Baru (+5 XP)
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
